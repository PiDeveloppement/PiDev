<?php


namespace App\Command;

use App\Entity\User\UserModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class HashPasswordsCommand extends Command
{
    protected static $defaultName = 'app:hash-passwords';
    
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->entityManager->getRepository(UserModel::class)->findAll();
        
        foreach ($users as $user) {
            // Ne hasher que si le mot de passe n'est pas déjà hashé
            $password = $user->getPassword();
            if (!password_get_info($password)['algo']) { // Vérifie si c'est un hash
                $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);
                $output->writeln("Mot de passe hashé pour: " . $user->getEmail());
            }
        }
        
        $this->entityManager->flush();
        $output->writeln("Tous les mots de passe ont été hashés!");
        
        return Command::SUCCESS;
    }
}
