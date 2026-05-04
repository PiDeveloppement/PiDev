<?php

namespace App\Bundle\NotificationBundle\Service;

use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

class NotificationService
{
    public function __construct(
        private TexterInterface $texter
    ) {}

    public function sendSms(string $phone, string $message): void
    {
        error_log('=== NotificationService: Début envoi SMS ===');
        error_log('Numéro: ' . $phone);
        error_log('Message: ' . $message);
        
        try {
            $sms = new SmsMessage($phone, $message);
            error_log('SmsMessage créé');
            
            $result = $this->texter->send($sms);
            error_log('SMS envoyé via TexterInterface');
            error_log('Résultat: ' . print_r($result, true));
        } catch (\Exception $e) {
            error_log('Erreur envoi SMS: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}