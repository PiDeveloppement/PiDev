<?php

namespace App\Command;

use App\Entity\Resource\Salle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:update-salles-coordinates',
    description: 'Met à jour les coordonnées GPS des salles sans localisation'
)]
class UpdateSallesCoordinatesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->entityManager->getRepository(Salle::class);
        $salles = $repository->findAll();

        // Coordonnées approximatives pour chaque bloc (Tunis)
        $coordinates = [
            'A' => ['lat' => 36.8602, 'lng' => 10.1905],
            'B' => ['lat' => 36.8612, 'lng' => 10.1915],
            'C' => ['lat' => 36.8622, 'lng' => 10.1925],
            'D' => ['lat' => 36.8632, 'lng' => 10.1935],
            'G' => ['lat' => 36.8642, 'lng' => 10.1945],
            'M' => ['lat' => 36.8652, 'lng' => 10.1955],
            'IJK' => ['lat' => 36.8662, 'lng' => 10.1965],
            'ijk' => ['lat' => 36.8662, 'lng' => 10.1965], // pour la salle k2
        ];

        $updatedCount = 0;

        foreach ($salles as $salle) {
            $building = strtoupper($salle->getBuilding());
            
            // Si les coordonnées sont nulles, 0,0 ou non définies
            if ($salle->getLatitude() === null || 
                $salle->getLongitude() === null || 
                ($salle->getLatitude() == 0 && $salle->getLongitude() == 0)) {
                
                if (isset($coordinates[$building])) {
                    $salle->setLatitude($coordinates[$building]['lat']);
                    $salle->setLongitude($coordinates[$building]['lng']);
                    $updatedCount++;
                    
                    $output->writeln(sprintf(
                        '✅ Salle %s (%s) - Coordonnées mises à jour: %s, %s',
                        $salle->getName(),
                        $salle->getBuilding(),
                        $coordinates[$building]['lat'],
                        $coordinates[$building]['lng']
                    ));
                } else {
                    $output->writeln(sprintf(
                        '⚠️  Salle %s (%s) - Bloc non trouvé dans la configuration',
                        $salle->getName(),
                        $salle->getBuilding()
                    ));
                }
            } else {
                $output->writeln(sprintf(
                    'ℹ️  Salle %s (%s) - Coordonnées déjà définies: %s, %s',
                    $salle->getName(),
                    $salle->getBuilding(),
                    $salle->getLatitude(),
                    $salle->getLongitude()
                ));
            }
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('\n🎉 Mise à jour terminée! %d salles ont été mises à jour.', $updatedCount));

        return Command::SUCCESS;
    }
}