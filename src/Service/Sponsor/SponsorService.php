<?php

namespace App\Service\Sponsor;

use App\Entity\Sponsor\Sponsor;
use App\Entity\User\UserModel;
use App\Repository\Sponsor\SponsorRepository;
use App\Repository\User\UserRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

class SponsorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SponsorRepository $sponsorRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @return array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}>
     */
    public function fetchEventsCatalog(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, title, description, location, start_date, end_date FROM event';
        $params = [];

        if ($activeOnly) {
            $sql .= ' WHERE end_date IS NULL OR end_date >= :now';
            $params['now'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $sql .= ' ORDER BY start_date ASC';
        $rows = $this->entityManager->getConnection()->fetchAllAssociative($sql, $params);

        return array_map(static fn (array $row): array => self::mapEventRow($row), $rows);
    }

    /**
     * @return array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}>
     */
    public function fetchActiveEvents(): array
    {
        return $this->fetchEventsCatalog(true);
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}>|null $events
     * @return array<string,int>
     */
    public function buildEventChoices(?array $events = null): array
    {
        $source = $events ?? $this->fetchEventsCatalog();
        $choices = [];

        foreach ($source as $event) {
            $label = sprintf(
                '%s (%s)',
                $event['title'],
                $event['startDate'] instanceof \DateTimeInterface ? $event['startDate']->format('Y-m-d H:i') : '-'
            );
            $choices[$label] = (int) $event['id'];
        }

        return $choices;
    }

    /**
     * @return array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}|null
     */
    public function findEventById(int $eventId): ?array
    {
        if ($eventId <= 0) {
            return null;
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, title, description, location, start_date, end_date FROM event WHERE id = :id LIMIT 1',
            ['id' => $eventId]
        );

        if ($rows === []) {
            return null;
        }

        return self::mapEventRow($rows[0]);
    }

    /**
     * @param Sponsor[] $sponsors
     * @return array<int,string>
     */
    public function buildEventTitleMapForSponsors(array $sponsors): array
    {
        $eventIds = [];
        foreach ($sponsors as $sponsor) {
            $eventIds[] = (int) $sponsor->getEventId();
        }

        return $this->sponsorRepository->getEventTitleMap($eventIds);
    }

    public function getEventTitleById(int $eventId): ?string
    {
        if ($eventId <= 0) {
            return null;
        }

        $map = $this->sponsorRepository->getEventTitleMap([$eventId]);
        return $map[$eventId] ?? null;
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}> $events
     * @return array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}>
     */
    public function buildRecommendedEvents(array $events, UserModel $user, string $email): array
    {
        $industry = trim((string) ($user->getBio() ?? ''));
        if ($industry === '') {
            $industry = trim((string) ($this->sponsorRepository->findLastIndustryByEmail($email) ?? ''));
        }

        if ($industry === '') {
            return array_slice($events, 0, 6);
        }

        $tokens = array_values(array_filter(
            preg_split('/\s+/', mb_strtolower($industry)) ?: [],
            static fn (string $value): bool => mb_strlen($value) >= 3
        ));

        if ($tokens === []) {
            return array_slice($events, 0, 6);
        }

        $scored = [];
        foreach ($events as $event) {
            $haystack = mb_strtolower(
                (string) ($event['title'] ?? '') . ' ' .
                (string) ($event['description'] ?? '') . ' ' .
                (string) ($event['location'] ?? '')
            );

            $score = 0;
            foreach ($tokens as $token) {
                if (str_contains($haystack, $token)) {
                    ++$score;
                }
            }

            if ($score > 0) {
                $scored[] = ['event' => $event, 'score' => $score];
            }
        }

        if ($scored === []) {
            return array_slice($events, 0, 6);
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(static fn (array $row): array => $row['event'], array_slice($scored, 0, 6));
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}> $events
     * @return array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}>
     */
    public function sortEvents(array $events, string $sort): array
    {
        $sorted = $events;

        usort($sorted, static function (array $a, array $b) use ($sort): int {
            $aDate = $a['startDate'] instanceof \DateTimeInterface ? $a['startDate']->getTimestamp() : 0;
            $bDate = $b['startDate'] instanceof \DateTimeInterface ? $b['startDate']->getTimestamp() : 0;
            $aTitle = mb_strtolower((string) ($a['title'] ?? ''));
            $bTitle = mb_strtolower((string) ($b['title'] ?? ''));
            $aLocation = mb_strtolower((string) ($a['location'] ?? ''));
            $bLocation = mb_strtolower((string) ($b['location'] ?? ''));

            return match ($sort) {
                'date_desc' => $bDate <=> $aDate,
                'title_asc' => $aTitle <=> $bTitle,
                'title_desc' => $bTitle <=> $aTitle,
                'location_asc' => $aLocation <=> $bLocation,
                default => $aDate <=> $bDate,
            };
        });

        return $sorted;
    }

    /**
     * @param Sponsor[] $items
     * @return Sponsor[]
     */
    public function sortSponsors(array $items, string $sort): array
    {
        $sorted = $items;

        usort($sorted, static function (Sponsor $a, Sponsor $b) use ($sort): int {
            return match ($sort) {
                'amount_asc' => $a->getContributionAmount() <=> $b->getContributionAmount(),
                'amount_desc' => $b->getContributionAmount() <=> $a->getContributionAmount(),
                'company_asc' => mb_strtolower((string) $a->getCompanyName()) <=> mb_strtolower((string) $b->getCompanyName()),
                default => ($b->getId() ?? 0) <=> ($a->getId() ?? 0),
            };
        });

        return $sorted;
    }

    /**
     * @param array<string,int|float> $map
     * @return array<int,array{label:string,value:float,percent:float}>
     */
    public function toBarRows(array $map, int $limit = 6): array
    {
        if ($map === []) {
            return [];
        }

        arsort($map);
        $slice = array_slice($map, 0, max(1, $limit), true);
        $max = max(array_map(static fn ($value): float => (float) $value, $slice));
        $rows = [];

        foreach ($slice as $label => $value) {
            $numeric = (float) $value;
            $rows[] = [
                'label' => (string) $label,
                'value' => $numeric,
                'percent' => $max > 0 ? ($numeric / $max) * 100 : 0,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}> $events
     * @return array<string,int>
     */
    public function buildEventTypeStats(array $events): array
    {
        $stats = [
            'Conference' => 0,
            'Atelier' => 0,
            'Seminaire' => 0,
            'Formation' => 0,
            'Autre' => 0,
        ];

        foreach ($events as $event) {
            $title = mb_strtolower((string) ($event['title'] ?? ''));
            if (str_contains($title, 'atelier')) {
                ++$stats['Atelier'];
                continue;
            }
            if (str_contains($title, 'seminaire') || str_contains($title, 'seminar')) {
                ++$stats['Seminaire'];
                continue;
            }
            if (str_contains($title, 'formation') || str_contains($title, 'workshop')) {
                ++$stats['Formation'];
                continue;
            }
            if (str_contains($title, 'conference')) {
                ++$stats['Conference'];
                continue;
            }
            ++$stats['Autre'];
        }

        return array_filter($stats, static fn (int $count): bool => $count > 0);
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}> $events
     * @return array<string,int>
     */
    public function buildEventMonthStats(array $events, int $maxMonths = 6): array
    {
        $now = new \DateTimeImmutable('first day of this month 00:00:00');
        $stats = [];

        for ($i = 0; $i < $maxMonths; ++$i) {
            $month = $now->modify('+' . $i . ' month');
            if (!$month instanceof \DateTimeImmutable) {
                continue;
            }
            $key = $month->format('Y-m');
            $stats[$key] = ['label' => $month->format('m/Y'), 'count' => 0];
        }

        foreach ($events as $event) {
            if (!$event['startDate'] instanceof \DateTimeInterface) {
                continue;
            }

            $key = $event['startDate']->format('Y-m');
            if (isset($stats[$key])) {
                ++$stats[$key]['count'];
            }
        }

        $flat = [];
        foreach ($stats as $row) {
            $flat[(string) $row['label']] = (int) $row['count'];
        }

        return $flat;
    }

    /**
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}> $events
     * @return array{sponsorable: array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}>, archived: array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}>, ongoingCount:int}
     */
    public function splitEventsByStatus(array $events): array
    {
        $sponsorable = [];
        $archived = [];
        $ongoingCount = 0;

        foreach ($events as $event) {
            $status = $this->resolveEventStatus($event['startDate'] ?? null, $event['endDate'] ?? null);
            if ($status['key'] === 'termine') {
                $archived[] = $event;
                continue;
            }

            if ($status['key'] === 'en_cours') {
                ++$ongoingCount;
            }

            $sponsorable[] = $event;
        }

        return [
            'sponsorable' => $sponsorable,
            'archived' => $archived,
            'ongoingCount' => $ongoingCount,
        ];
    }

    /**
     * @param Sponsor[] $sponsors
     * @return array<int,array{id:int,eventId:int,company:string,contribution:float,eventTitle:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable,statusKey:string,statusLabel:string,statusClass:string,logoUrl:?string,contractUrl:?string}>
     */
    public function buildSponsorHistory(array $sponsors): array
    {
        if ($sponsors === []) {
            return [];
        }

        $eventMap = $this->getEventDetailsMap(array_map(
            static fn (Sponsor $sponsor): int => (int) $sponsor->getEventId(),
            $sponsors
        ));

        $items = [];
        foreach ($sponsors as $sponsor) {
            $eventId = (int) $sponsor->getEventId();
            $event = $eventMap[$eventId] ?? null;
            $status = $this->resolveEventStatus($event['startDate'] ?? null, $event['endDate'] ?? null);

            $items[] = [
                'id' => (int) ($sponsor->getId() ?? 0),
                'eventId' => $eventId,
                'company' => (string) ($sponsor->getCompanyName() ?? '-'),
                'contribution' => $sponsor->getContributionAmount(),
                'eventTitle' => (string) ($event['title'] ?? '-'),
                'location' => (string) ($event['location'] ?? '-'),
                'startDate' => $event['startDate'] ?? null,
                'endDate' => $event['endDate'] ?? null,
                'statusKey' => $status['key'],
                'statusLabel' => $status['label'],
                'statusClass' => $status['class'],
                'logoUrl' => $sponsor->getLogoUrl(),
                'contractUrl' => $sponsor->getContractUrl(),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $aTime = $a['startDate'] instanceof \DateTimeInterface ? $a['startDate']->getTimestamp() : 0;
            $bTime = $b['startDate'] instanceof \DateTimeInterface ? $b['startDate']->getTimestamp() : 0;
            return $bTime <=> $aTime;
        });

        return $items;
    }

    /**
     * @param int[] $eventIds
     * @return array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}>
     */
    public function getEventDetailsMap(array $eventIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, title, description, location, start_date, end_date FROM event WHERE id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER]
        );

        $map = [];
        foreach ($rows as $row) {
            $event = self::mapEventRow($row);
            $map[$event['id']] = $event;
        }

        return $map;
    }

    /**
     * @return array{key:string,label:string,class:string}
     */
    public function resolveEventStatus(?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): array
    {
        $now = new \DateTimeImmutable();

        if ($endDate instanceof \DateTimeInterface && $endDate < $now) {
            return ['key' => 'termine', 'label' => 'Termine', 'class' => 'ended'];
        }

        if ($startDate instanceof \DateTimeInterface && $startDate <= $now) {
            return ['key' => 'en_cours', 'label' => 'En cours', 'class' => 'live'];
        }

        return ['key' => 'a_venir', 'label' => 'A venir', 'class' => 'upcoming'];
    }

    public function hydrateSponsorUserRelation(Sponsor $sponsor): void
    {
        $email = trim((string) $sponsor->getContactEmail());
        if ($email === '') {
            $sponsor->setUser(null);
            return;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $sponsor->setUser($user instanceof UserModel ? $user : null);
    }

    public function hasDuplicateSponsor(Sponsor $sponsor, ?int $excludeId = null): bool
    {
        $email = mb_strtolower(trim((string) $sponsor->getContactEmail()));
        $companyName = mb_strtolower(trim((string) $sponsor->getCompanyName()));
        $eventId = (int) ($sponsor->getEventId() ?? 0);

        if ($email === '' || $companyName === '' || $eventId <= 0) {
            return false;
        }

        $qb = $this->sponsorRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('LOWER(s.contactEmail) = :email')
            ->andWhere('LOWER(s.companyName) = :companyName')
            ->andWhere('s.eventId = :eventId')
            ->setParameter('email', $email)
            ->setParameter('companyName', $companyName)
            ->setParameter('eventId', $eventId);

        if ($excludeId !== null && $excludeId > 0) {
            $qb->andWhere('s.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function canCurrentUserManageSponsor(UserModel $user, Sponsor $sponsor): bool
    {
        if ($this->isAdminUser($user)) {
            return true;
        }

        $email = mb_strtolower(trim((string) $user->getEmail()));
        $sponsorEmail = mb_strtolower(trim((string) $sponsor->getContactEmail()));

        return $email !== '' && $email === $sponsorEmail;
    }

    public function isAdminUser(UserModel $user): bool
    {
        $roleName = mb_strtolower((string) ($user->getRole()?->getRoleName() ?? ''));
        $roleId = (int) ($user->getRoleId() ?? 0);

        return $roleName === 'admin' || $roleId === 4;
    }

    public function isSponsorUser(UserModel $user): bool
    {
        $roleName = mb_strtolower((string) ($user->getRole()?->getRoleName() ?? ''));
        $roleId = (int) ($user->getRoleId() ?? 0);

        return $roleName === 'sponsor' || $roleId === 3;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}
     */
    private static function mapEventRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'startDate' => !empty($row['start_date']) ? new \DateTimeImmutable((string) $row['start_date']) : null,
            'endDate' => !empty($row['end_date']) ? new \DateTimeImmutable((string) $row['end_date']) : null,
        ];
    }
}
