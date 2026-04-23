<?php

namespace App\Command;

use App\Entity\User\UserModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:clean-face-descriptors')]
class CleanFaceDescriptorsCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Nettoyage des descripteurs faciaux');

        $users = $this->em->getRepository(UserModel::class)
            ->createQueryBuilder('u')
            ->where('u.faceDescriptor IS NOT NULL')
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            $io->success('Aucun descripteur facial trouvé dans la base de données.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Trouvé %d utilisateur(s) avec des descripteurs faciaux.', count($users)));

        $io->confirm('Voulez-vous vraiment supprimer tous les descripteurs faciaux ?', false);

        $count = 0;
        foreach ($users as $user) {
            $user->setFaceDescriptor(null);
            $count++;
        }

        $this->em->flush();

        $io->success(sprintf('%d descripteur(s) facial(aux) supprimé(s).', $count));
        $io->note('Les utilisateurs devront se réinscrire pour configurer leur Face ID.');

        return Command::SUCCESS;
    }
}
