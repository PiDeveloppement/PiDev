<?php

namespace App\Command;

use App\Entity\Event\Event;
use App\Repository\Event\EventRepository;
use App\Service\Event\EventService;
use App\Service\Event\GoogleCalendarWriteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:debug:google-inbound-sync', description: 'Diagnose and run Google inbound sync')]
class DebugGoogleInboundSyncCommand extends Command
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventService $eventService,
        private readonly GoogleCalendarWriteService $googleService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $reflection = new \ReflectionClass($this->googleService);

        $calendarId = $this->readPrivateString($reflection, 'calendarId');
        $clientId = $this->readPrivateString($reflection, 'clientId');
        $clientSecret = $this->readPrivateString($reflection, 'clientSecret');
        $refreshToken = $this->readPrivateString($reflection, 'refreshToken');
        $timezone = $this->readPrivateString($reflection, 'timezone');

        $io->section('Google runtime configuration');
        $io->table(['Setting', 'Value'], [
            ['GOOGLE_CALENDAR_ID', $calendarId !== '' ? $calendarId : '[EMPTY]'],
            ['GOOGLE_CLIENT_ID', $clientId !== '' ? '[SET]' : '[EMPTY]'],
            ['GOOGLE_CLIENT_SECRET', $clientSecret !== '' ? '[SET]' : '[EMPTY]'],
            ['GOOGLE_REFRESH_TOKEN', $refreshToken !== '' ? '[SET]' : '[EMPTY]'],
            ['GOOGLE_CALENDAR_TIMEZONE', $timezone !== '' ? $timezone : '[EMPTY -> default Africa/Tunis]'],
        ]);

        $io->section('Inbound sync execution');
        $stats = $this->eventService->syncFromGoogleCalendarToEventFlow();

        $io->table(['updated', 'deleted', 'failed', 'skipped'], [[
            (int) ($stats['updated'] ?? 0),
            (int) ($stats['deleted'] ?? 0),
            (int) ($stats['failed'] ?? 0),
            (int) ($stats['skipped'] ?? 0),
        ]]);

        if (($stats['failed'] ?? 0) > 0) {
            $io->warning('Some remote fetches failed. Last service error: ' . ((string) ($this->googleService->getLastError() ?? 'n/a')));
        }

        $io->section('Per-event inbound check');
        $rows = [];
        foreach ($this->eventRepository->findAllOrderedByDate() as $event) {
            if (!$event instanceof Event || !$event->getId()) {
                continue;
            }

            $remote = $this->googleService->fetchRemoteEventByLocalId((int) $event->getId(), $event);
            $found = (bool) ($remote['found'] ?? false);
            $ok = (bool) ($remote['ok'] ?? false);
            $remoteTitle = $found ? (string) (($remote['event']['title'] ?? '') ?: '-') : '-';
            $localTitle = (string) ($event->getTitle() ?? '-');

            $rows[] = [
                (string) $event->getId(),
                $ok ? 'ok' : 'ko',
                $found ? 'yes' : 'no',
                $localTitle,
                $remoteTitle,
                $found && $remoteTitle !== $localTitle ? 'title differs' : '-',
            ];
        }

        if ($rows !== []) {
            $io->table(['id', 'api', 'found', 'local title', 'remote title', 'note'], $rows);
        }

        $io->success('Diagnostic finished.');
        return Command::SUCCESS;
    }

    private function readPrivateString(\ReflectionClass $reflection, string $property): string
    {
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $value = $prop->getValue($this->googleService);

        return is_string($value) ? trim($value) : '';
    }
}
