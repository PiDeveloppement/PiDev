<?php

namespace App\Controller\Resource;

use App\Entity\Resource\ReservationResource;
use App\Entity\Resource\Salle;
use App\Entity\Resource\Equipement;
use App\Form\Resource\ReservationType;
use App\Repository\Resource\ReservationResourceRepository;
use App\Repository\Resource\SalleRepository;
use App\Repository\Resource\EquipementRepository;
use App\Service\Resource\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Twig\Environment;

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
    public function new(Request $request, EntityManagerInterface $em, MailerService $mailerService): Response
    {
        $reservation = new ReservationResource();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($reservation);
            $em->flush();
            
            // Préparer les données pour l'email
            $reservationData = [
                'userName' => $this->getUser()->getFullName() ?? $this->getUser()->getEmail(),
                'email' => $this->getUser()->getEmail(),
                'resourceType' => $reservation->getResourceType(),
                'eventName' => $reservation->getEvent()->getTitle(),
                'startTime' => $reservation->getStartTime(),
                'endTime' => $reservation->getEndTime(),
                'quantity' => $reservation->getQuantity()
            ];
            
            if ($reservation->getResourceType() === 'SALLE') {
                $reservationData['salleName'] = $reservation->getSalle()->getName();
            } else {
                $reservationData['equipementName'] = $reservation->getEquipement()->getName();
            }
            
            // Envoyer l'email de confirmation à l'utilisateur
            $mailerService->sendReservationConfirmation($reservationData);
            
            // Envoyer la notification à l'administrateur
            $mailerService->sendReservationNotification($reservationData);
            
            $this->addFlash('success', 'Réservation créée avec succès ! Un email de confirmation vous a été envoyé.');
            return $this->redirectToRoute('app_reservation_resource_index');
        }

        return $this->render('resource/reservation_resource/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/ajax/resources', name: 'app_ajax_resources', methods: ['GET'])]
    public function getResources(Request $request, SalleRepository $salleRepo, EquipementRepository $equipementRepo, ReservationResourceRepository $resRepo): JsonResponse
    {
        $type = $request->query->get('type');
        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');
        
        // Only set dates if BOTH start and end are provided
        $startDate = null;
        $endDate = null;
        if ($startStr && $endStr) {
            $startDate = new \DateTime($startStr);
            $endDate = new \DateTime($endStr);
        }

        $resources = [];

        if ($type === 'SALLE') {
            $salles = $salleRepo->findAll();
            foreach ($salles as $salle) {
                $isReserved = false;
                // Only check for conflicts if BOTH dates are provided
                if ($startDate && $endDate) {
                    // Vérifier si la salle est déjà prise sur ce créneau
                    $isReserved = $resRepo->createQueryBuilder('r')
                        ->select('count(r.id)')
                        ->where('r.salle = :salle')
                        ->andWhere('r.startTime < :end AND r.endTime > :start')
                        ->setParameter('salle', $salle)
                        ->setParameter('start', $startDate)
                        ->setParameter('end', $endDate)
                        ->getQuery()->getSingleScalarResult() > 0;
                }

                $resources[] = [
                    'id' => $salle->getId(),
                    'name' => $salle->getName(),
                    'available' => ($salle->getStatus() === 'DISPONIBLE' && !$isReserved),
                    'info' => $isReserved ? 'Déjà réservée' : 'Capacité: ' . $salle->getCapacity()
                ];
            }
        } elseif ($type === 'EQUIPEMENT') {
            $equipements = $equipementRepo->findAll();
            foreach ($equipements as $equip) {
                $totalReserved = 0;
                // Only check for conflicts if BOTH dates are provided
                if ($startDate && $endDate) {
                    // Sommer les quantités réservées pour cet équipement sur la période
                    $result = $resRepo->createQueryBuilder('r')
                        ->select('SUM(r.quantity)')
                        ->where('r.equipement = :equip')
                        ->andWhere('r.startTime < :end AND r.endTime > :start')
                        ->setParameter('equip', $equip)
                        ->setParameter('start', $startDate)
                        ->setParameter('end', $endDate)
                        ->getQuery()->getSingleScalarResult();
                    
                    // Handle null result from SUM when no reservations exist
                    $totalReserved = (int)($result ?? 0);
                }

                $stockInitial = $equip->getQuantity();
                // If no dates selected, show full available stock
                $remaining = $stockInitial - $totalReserved;

                $resources[] = [
                    'id' => $equip->getId(),
                    'name' => $equip->getName(),
                    // Mark as unavailable if: status is not DISPONIBLE OR (dates selected AND no stock remains)
                    'available' => ($equip->getStatus() === 'DISPONIBLE') 
                        && (($startDate && $endDate) ? ($remaining > 0) : true),
                    'info' => ($startDate && $endDate) 
                        ? ('Dispo: ' . ($remaining > 0 ? $remaining : 'Épuisé') . ' (Total: ' . $stockInitial . ')')
                        : ('Dispo: ' . $stockInitial . ' (Total: ' . $stockInitial . ')')
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

    #[Route('/export-pdf', name: 'app_reservation_resource_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, ReservationResourceRepository $repo, Environment $twig): Response
    {
        // Récupérer les filtres et le tri
        $filters = [
            'name' => $request->query->get('name'),
            'resourceType' => $request->query->get('resourceType'),
        ];

        $sortBy = $request->query->get('sortBy', 'startTime');
        $direction = $request->query->get('direction', 'desc');

        $reservations = $repo->findByFilters($filters, $sortBy, $direction);

        // Générer le HTML
        $html = $twig->render('resource/reservation_resource/pdf.html.twig', [
            'reservations' => $reservations,
            'filters' => $filters,
        ]);

        // Créer le PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Retourner le PDF
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="reservations_' . date('Y-m-d_H-i-s') . '.pdf"'
            ]
        );
    }
}