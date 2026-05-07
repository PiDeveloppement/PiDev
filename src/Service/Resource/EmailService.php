<?php

namespace App\Service\Resource;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    private MailerInterface $mailer;
    private string $fromEmail;
    private string $fromName;

    public function __construct(MailerInterface $mailer, string $fromEmail = 'mecherguisouhail8@gmail.com', string $fromName = 'PiDev Platform')
    {
        $this->mailer = $mailer;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    /**
     * Envoie un email de confirmation de réservation
     * @param array{resource_name: string, start_time: string, end_time: string, quantity?: int, status?: string} $reservationData
     */
    public function sendReservationConfirmation(string $toEmail, string $userName, array $reservationData): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject('Confirmation de votre réservation - PiDev Platform')
            ->htmlTemplate('Resource/emails/reservation_confirmation.html.twig')
            ->context([
                'user_name' => $userName,
                'reservation' => $reservationData,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie une notification email
     * @param array<string, mixed> $context
     */
    public function sendNotification(string $toEmail, string $subject, string $message, array $context = []): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject($subject)
            ->htmlTemplate('Resource/emails/notification.html.twig')
            ->context(array_merge([
                'message' => $message,
                'subject' => $subject,
            ], $context));

        $this->mailer->send($email);
    }

    /**
     * Envoie un email de rappel de réservation
     * @param array{resource_name: string, start_time: string, end_time: string, quantity?: int, status?: string} $reservationData
     */
    public function sendReservationReminder(string $toEmail, string $userName, array $reservationData): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject('Rappel de votre réservation - PiDev Platform')
            ->htmlTemplate('Resource/emails/reservation_reminder.html.twig')
            ->context([
                'user_name' => $userName,
                'reservation' => $reservationData,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie un email d'annulation de réservation
     * @param array{resource_name: string, start_time: string, end_time: string, quantity?: int, status?: string} $reservationData
     */
    public function sendReservationCancellation(string $toEmail, string $userName, array $reservationData): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject('Annulation de réservation - PiDev Platform')
            ->htmlTemplate('Resource/emails/reservation_cancellation.html.twig')
            ->context([
                'user_name' => $userName,
                'reservation' => $reservationData,
            ]);

        $this->mailer->send($email);
    }
}