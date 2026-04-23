<?php

namespace App\Command;

use App\Service\User\EmailService;
use App\Entity\User\UserModel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-email')]
class TestEmailCommand extends Command
{
    public function __construct(private EmailService $emailService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('📧 Test d\'envoi d\'email...');

        // Créer un utilisateur de test
        $testUser = new UserModel();
        $testUser->setFirstName('Test');
        $testUser->setLastName('User');
        $testUser->setEmail('sellamiarij7@gmail.com');
        $testUser->setFaculte('Test Faculté');

        $result = $this->emailService->sendWelcomeEmail($testUser);

        if ($result) {
            $output->writeln('✅ Email envoyé avec succès !');
            return Command::SUCCESS;
        } else {
            $output->writeln('❌ Échec de l\'envoi d\'email. Vérifiez les logs.');
            return Command::FAILURE;
        }
    }
}
