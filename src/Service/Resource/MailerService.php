<?php

namespace App\Service\Resource;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Environment;

class MailerService
{
    private MailerInterface $mailer;
    private Environment $twig;

    public function __construct(MailerInterface $mailer, Environment $twig)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    /**
     * Envoie un email de confirmation de réservation
     * @param array{email: string, resource_name: string, start_time: string, end_time: string, quantity?: int, user_name?: string} $reservationData
     */
    public function sendReservationConfirmation(array $reservationData): bool
    {
        try {
            $email = (new Email())
                ->from('xknx+cbvx+piuc+upbb@gmail.com')
                ->to($reservationData['email'])
                ->subject('Confirmation de votre réservation')
                ->html($this->twig->render('Resource/emails/reservation_confirmation.html.twig', [
                    'reservation' => $reservationData
                ]));

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            // Log l'erreur ou gérer l'exception
            return false;
        }
    }

    /**
     * Envoie une notification de nouvelle réservation à l'administrateur
     * @param array{email: string, resource_name: string, start_time: string, end_time: string, quantity?: int, user_name?: string} $reservationData
     */
    public function sendReservationNotification(array $reservationData): bool
    {
        try {
            $email = (new Email())
                ->from('xknx+cbvx+piuc+upbb@gmail.com')
                ->to('admin@gmail.com') // Email de l'administrateur
                ->subject('Nouvelle réservation effectuée')
                ->html($this->twig->render('Resource/emails/reservation_notification.html.twig', [
                    'reservation' => $reservationData
                ]));

            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            // Log l'erreur ou gérer l'exception
            return false;
        }
    }
}