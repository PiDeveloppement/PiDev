<?php

namespace App\Command;

use App\Repository\Resource\ReservationResourceRepository;
use App\Service\Resource\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-reservation-reminders',
    description: 'Envoie des emails de rappel pour les réservations de demain'
)]
class SendReservationRemindersCommand extends Command
{
    private ReservationResourceRepository $reservationRepository;
    private EmailService $emailService;

    public function __construct(ReservationResourceRepository $reservationRepository, EmailService $emailService)
    {
        $this->reservationRepository = $reservationRepository;
        $this->emailService = $emailService;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('📧 Envoi des emails de rappel de réservation');

        // Récupérer la date de demain
        $tomorrow = new \DateTime('tomorrow');
        $tomorrow->setTime(0, 0, 0);
        $endOfTomorrow = clone $tomorrow;
        $endOfTomorrow->setTime(23, 59, 59);

        // Récupérer les réservations de demain
        $reservations = $this->reservationRepository->createQueryBuilder('r')
            ->where('r.startTime >= :start')
            ->andWhere('r.startTime <= :end')
            ->setParameter('start', $tomorrow)
            ->setParameter('end', $endOfTomorrow)
            ->getQuery()
            ->getResult();

        if (empty($reservations)) {
            $io->success('Aucune réservation pour demain. Aucun email à envoyer.');
            return Command::SUCCESS;
        }

        $emailsSent = 0;
        $emailsFailed = 0;

        foreach ($reservations as $reservation) {
            try {
                // Préparer les données pour l'email
                $user = $reservation->getUser(); // Assurez-vous que la relation user existe
                if (!$user) {
                    $io->warning("Réservation #{$reservation->getId()} sans utilisateur associé. Email ignoré.");
                    continue;
                }

                $userName = $user->getFullName() ?? $user->getEmail();
                $userEmail = $user->getEmail();

                $reservationData = [
                    'id' => $reservation->getId(),
                    'resourceType' => $reservation->getResourceType(),
                    'dateReservation' => $reservation->getStartTime(),
                    'heureDebut' => $reservation->getStartTime(),
                    'heureFin' => $reservation->getEndTime(),
                    'motif' => $reservation->getEvent() ? $reservation->getEvent()->getTitle() : 'Réservation de ressource',
                    'quantity' => $reservation->getQuantity()
                ];

                if ($reservation->getResourceType() === 'SALLE' && $reservation->getSalle()) {
                    $reservationData['salle'] = $reservation->getSalle();
                } elseif ($reservation->getResourceType() === 'EQUIPEMENT' && $reservation->getEquipement()) {
                    $reservationData['equipement'] = $reservation->getEquipement();
                }

                // Envoyer l'email de rappel
                $this->emailService->sendReservationReminder($userEmail, $userName, $reservationData);
                $emailsSent++;

                $io->text("✅ Email de rappel envoyé à {$userEmail} pour la réservation #{$reservation->getId()}");

            } catch (\Exception $e) {
                $emailsFailed++;
                $io->error("❌ Erreur lors de l'envoi de l'email pour la réservation #{$reservation->getId()}: " . $e->getMessage());
            }
        }

        $io->newLine();
        $io->section('Résumé');
        $io->success("Emails envoyés avec succès: {$emailsSent}");
        
        if ($emailsFailed > 0) {
            $io->error("Échecs d'envoi: {$emailsFailed}");
        }

        return Command::SUCCESS;
    }
}
