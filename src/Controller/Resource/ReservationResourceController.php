<?php

namespace App\Controller\Resource;

use App\Entity\Resource\ReservationResource;
use App\Entity\Resource\Salle;
use App\Entity\Resource\Equipement;
use App\Form\Resource\ReservationType;
use App\Repository\Resource\ReservationResourceRepository;
use App\Repository\Resource\SalleRepository;
use App\Repository\Resource\EquipementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/resource/reservation')]
class ReservationResourceController extends AbstractController
{
    #[Route('/', name: 'app_reservation_resource_index', methods: ['GET'])]
    public function index(Request $request, ReservationResourceRepository $repo): Response
    {
        $filters = [
            'name' => $request->query->get('name'),
            'resourceType' => $request->query->get('resourceType'),
        ];

        $sortBy = $request->query->get('sortBy', 'startTime');
        $direction = $request->query->get('direction', 'desc');

        $reservations = $repo->findByFilters($filters, $sortBy, $direction);

        return $this->render('resource/reservation_resource/index.html.twig', [
            'reservations' => $reservations,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'direction' => $direction,
        ]);
    }

    #[Route('/new', name: 'app_reservation_resource_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $reservation = new ReservationResource();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // La validation se fait maintenant dans l'entité avec les assertions
            $resourceType = $form->get('resourceType')->getData();
            $reservation->setResourceType($resourceType);
            
            // Get selected resource from custom select field
            $resourceId = $request->request->get('resourceSelect');
            $quantity = $form->get('quantity')->getData();
            
            if ($resourceType === 'SALLE' && $resourceId) {
                $salle = $em->find(Salle::class, $resourceId);
                $reservation->setSalle($salle);
                $reservation->setEquipement(null);
                
                // Calculer et mettre à jour la quantité restante
                $this->updateSalleAvailability($salle, $quantity, $em, $reservation->getStartTime(), $reservation->getEndTime());
                
            } elseif ($resourceType === 'EQUIPEMENT' && $resourceId) {
                $equipement = $em->find(Equipement::class, $resourceId);
                $reservation->setEquipement($equipement);
                $reservation->setSalle(null);
                
                // Calculer et mettre à jour la quantité restante
                $this->updateEquipementAvailability($equipement, $quantity, $em, $reservation->getStartTime(), $reservation->getEndTime());
            }
            
            $em->persist($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation créée avec succès !');
            return $this->redirectToRoute('app_reservation_resource_index');
        }

        return $this->render('resource/reservation_resource/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Met à jour la disponibilité d'une salle après une réservation
     */
    private function updateSalleAvailability(Salle $salle, int $reservedQuantity, EntityManagerInterface $em, \DateTimeInterface $startDate, \DateTimeInterface $endDate): void
    {
        // Récupérer toutes les réservations pour cette salle à la même date
        $existingReservations = $em->getRepository(ReservationResource::class)
            ->createQueryBuilder('rr')
            ->where('rr.salle = :salle')
            ->andWhere('rr.startTime <= :startDate AND rr.endTime >= :endDate')
            ->setParameter('salle', $salle)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        // Calculer la quantité totale déjà réservée
        $totalReserved = 0;
        foreach ($existingReservations as $rr) {
            $totalReserved += $rr->getQuantity();
        }

        // Calculer la quantité restante
        $remainingQuantity = $salle->getCapacity() - $totalReserved;

        // Mettre à jour la capacité restante de la salle
        $salle->setCapacity($remainingQuantity);
        $em->persist($salle);
        $em->flush();
    }

    /**
     * Met à jour la disponibilité d'un équipement après une réservation
     */
    private function updateEquipementAvailability(Equipement $equipement, int $reservedQuantity, EntityManagerInterface $em, \DateTimeInterface $startDate, \DateTimeInterface $endDate): void
    {
        // Récupérer toutes les réservations pour cet équipement à la mêmes dates
        $existingReservations = $em->getRepository(ReservationResource::class)
            ->createQueryBuilder('rr')
            ->where('rr.equipement = :equipement')
            ->andWhere('rr.startTime <= :startDate AND rr.endTime >= :endDate')
            ->setParameter('equipement', $equipement)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        // Calculer la quantité totale déjà réservée
        $totalReserved = 0;
        foreach ($existingReservations as $rr) {
            $totalReserved += $rr->getQuantity();
        }

        // Calculer la quantité restante
        $remainingQuantity = $equipement->getQuantity() - $totalReserved;

        // Mettre à jour la quantité restante de l'équipement
        $equipement->setQuantity($remainingQuantity);
        $em->persist($equipement);
        $em->flush();
    }

    #[Route('/ajax/resources', name: 'app_ajax_resources', methods: ['GET'])]
    public function getResources(Request $request, SalleRepository $salleRepo, EquipementRepository $equipementRepo): JsonResponse
    {
        $type = $request->query->get('type');
        $resources = [];

        if ($type === 'SALLE') {
            $salles = $salleRepo->findAll();
            foreach ($salles as $salle) {
                $resources[] = [
                    'id' => $salle->getId(),
                    'name' => $salle->getName(),
                    'available' => $salle->getStatus() === 'DISPONIBLE',
                    'capacity' => $salle->getCapacity()
                ];
            }
        } elseif ($type === 'EQUIPEMENT') {
            $equipements = $equipementRepo->findAll();
            foreach ($equipements as $equipement) {
                $resources[] = [
                    'id' => $equipement->getId(),
                    'name' => $equipement->getName(),
                    'available' => $equipement->getStatus() === 'DISPONIBLE',
                    'quantity' => $equipement->getQuantity()
                ];
            }
        }

        return new JsonResponse($resources);
    }

    #[Route('/{id}/edit', name: 'app_reservation_resource_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ReservationResource $reservation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Réservation mise à jour !');
            return $this->redirectToRoute('app_reservation_resource_index');
        }

        return $this->render('resource/reservation_resource/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_reservation_resource_delete', methods: ['POST'])]
    public function delete(Request $request, ReservationResource $reservation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->request->get('_token'))) {
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }

        return $this->redirectToRoute('app_reservation_resource_index');
    }
}