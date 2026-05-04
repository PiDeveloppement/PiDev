<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$mailerDsn = $_ENV['MAILER_DSN'] ?? 'null://null';
echo "MAILER_DSN: " . $mailerDsn . "\n";

try {
    $transport = Transport::fromDsn($mailerDsn);
    $mailer = new Mailer($transport);

    $email = (new Email())
        ->from('test@eventflow.local')
        ->to('mecherguisouhail8@gmail.com')
        ->subject('Test email')
        ->text('This is a test email from Symfony Mailer');

    echo "Sending email...\n";
    $mailer->send($email);
    echo "Email sent successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error details: " . $e->getTraceAsString() . "\n";
}
