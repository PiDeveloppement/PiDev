<?php

namespace App\Controller\Resource;

use App\Entity\Resource\ReservationResource;
use App\Entity\Resource\Salle;
use App\Entity\Resource\Equipement;
use App\Form\Resource\ReservationType;
use App\Repository\Resource\ReservationResourceRepository;
use App\Repository\Resource\SalleRepository;
use App\Repository\Resource\EquipementRepository;
use App\Service\Resource\EmailService;
use App\Service\Resource\AuditService;
use App\Repository\Resource\HistoriqueLogRepository;
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
    public function index(Request $request, ReservationResourceRepository $repo, HistoriqueLogRepository $historiqueRepo): Response
    {
        $filters = [];
        $name = $request->query->get('name');
        if ($name && is_string($name)) {
            $filters['name'] = $name;
        }
        $resourceType = $request->query->get('resourceType');
        if ($resourceType && is_string($resourceType)) {
            $filters['resourceType'] = $resourceType;
        }

        $sortBy = $request->query->get('sortBy', 'startTime');
        $direction = $request->query->get('direction', 'desc');

        $reservations = $repo->findByFilters(
            $filters,
            is_string($sortBy) ? $sortBy : 'startTime',
            is_string($direction) ? $direction : 'desc'
        );

        // Charger les logs d'historique dynamiquement
        $auditLogs = $historiqueRepo->findRecentWithPagination(1, 10);
        
        // Formater les logs pour le template
        $auditLogsData = [];
        foreach ($auditLogs as $log) {
            $auditLogsData[] = [
                'id' => $log['id'],
                'action' => $log['action'],
                'actionLabel' => $this->getActionLabel($log['action']),
                'resourceType' => $log['resource_type'],
                'resourceTypeLabel' => $this->getResourceTypeLabel($log['resource_type']),
                'resourceId' => $log['resource_id'],
                'resourceName' => $log['resource_name'],
                'oldValues' => $log['old_values'],
                'newValues' => $log['new_values'],
                'changes' => $this->getChanges($log['old_values'], $log['new_values']),
                'createdAt' => $log['created_at'],
                'ipAddress' => $log['ip_address'],
                'userAgent' => $log['user_agent'],
                'userName' => $log['user_name'],
                'userEmail' => $log['user_email'],
                'description' => $this->getDescription([
                    'user_name' => $log['user_name'],
                    'created_at' => $log['created_at'],
                    'resource_name' => $log['resource_name'],
                    'action' => $log['action']
                ])
            ];
        }

        return $this->render('resource/reservation_resource/index.html.twig', [
            'reservations' => $reservations,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'direction' => $direction,
            'auditLogsData' => $auditLogsData,
        ]);
    }

    #[Route('/new', name: 'app_reservation_resource_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, EmailService $emailService, AuditService $auditService): Response
    {
        $reservation = new ReservationResource();
        
        // Pré-remplir les dates si elles sont passées en paramètre
        $dateParam = $request->query->get('date');
        if ($dateParam) {
            try {
                $date = new \DateTime((string) $dateParam);
                $reservation->setStartTime($date);
                $reservation->setEndTime((clone $date)->modify('+1 day'));
            } catch (\Exception $e) {
                // Ignorer si la date est invalide
            }
        }
        
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($reservation);
            $em->flush();
            
            // Logger la création dans l'audit
            $auditService->logCreate($reservation);
            
            // Préparer les données pour l'email
            $user = $this->getUser();
            $userName = 'Utilisateur';
            $userEmail = 'user@example.com';
            
            if ($user) {
                $userName = method_exists($user, 'getFullName') ? $user->getFullName() : 
                          (method_exists($user, 'getEmail') ? $user->getEmail() : 'Utilisateur');
                $userEmail = method_exists($user, 'getEmail') ? $user->getEmail() : 'user@example.com';
            }
            
            $reservationData = [
                'id' => $reservation->getId(),
                'resourceType' => $reservation->getResourceType(),
                'dateReservation' => $reservation->getStartTime(),
                'heureDebut' => $reservation->getStartTime(),
                'heureFin' => $reservation->getEndTime(),
                'motif' => $reservation->getEvent() ? $reservation->getEvent()->getTitle() : 'Réservation de ressource',
                'quantity' => $reservation->getQuantity()
            ];
            
            if ($reservation->getResourceType() === 'SALLE' && $reservation->getSalle()) {
                $reservationData['salle'] = $reservation->getSalle();
            } elseif ($reservation->getResourceType() === 'EQUIPEMENT' && $reservation->getEquipement()) {
                $reservationData['equipement'] = $reservation->getEquipement();
            }
            
            // Envoyer l'email de confirmation à l'utilisateur
            $resourceName = 'Ressource inconnue';
            if ($reservation->getResourceType() === 'SALLE' && $reservation->getSalle()) {
                $resourceName = $reservation->getSalle()->getName();
            } elseif ($reservation->getEquipement()) {
                $resourceName = $reservation->getEquipement()->getName();
            }
            
            $emailReservationData = [
                'resource_name' => $resourceName,
                'start_time' => $reservation->getStartTime()?->format('Y-m-d H:i:s') ?? '',
                'end_time' => $reservation->getEndTime()?->format('Y-m-d H:i:s') ?? '',
                'quantity' => $reservation->getQuantity() ?? 1,
                'status' => 'confirmée'
            ];
            $emailService->sendReservationConfirmation($userEmail, $userName, $emailReservationData);
            
            // Envoyer la notification à l'administrateur
            $startTime = $reservation->getStartTime();
            $dateStr = $startTime?->format('d/m/Y') ?? 'date inconnue';
            $emailService->sendNotification(
                'admin@pidev.com', 
                'Nouvelle réservation effectuée - ' . ($reservation->getEvent() ? $reservation->getEvent()->getTitle() : 'Réservation'),
                'Une nouvelle réservation a été effectuée par ' . $userName . ' pour le ' . $dateStr,
                ['user_name' => $userName, 'reservation' => $reservationData]
            );
            
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
            $startDate = new \DateTime((string) $startStr);
            $endDate = new \DateTime((string) $endStr);
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
    public function edit(Request $request, ReservationResourceRepository $repo, EntityManagerInterface $em, AuditService $auditService, int $id): Response
    {
        $reservation = $repo->find($id);
        
        if (!$reservation) {
            $this->addFlash('error', 'Réservation non trouvée.');
            return $this->redirectToRoute('app_reservation_resource_index');
        }
        
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capturer les anciennes valeurs avant la modification
            $oldValues = $auditService->extractEntityValues($reservation);
            
            $em->flush();
            
            // Capturer les nouvelles valeurs après la modification
            $newValues = $auditService->extractEntityValues($reservation);
            
            // Logger la modification dans l'audit avec les différences
            $changes = $auditService->getChangedValues($oldValues, $newValues);
            if (!empty($changes)) {
                $auditService->logUpdate($reservation, $oldValues, $newValues);
            }
            
            $this->addFlash('success', 'Réservation mise à jour !');
            return $this->redirectToRoute('app_reservation_resource_index');
        }

        return $this->render('resource/reservation_resource/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_reservation_resource_delete', methods: ['POST'])]
    public function delete(Request $request, ReservationResourceRepository $repo, EntityManagerInterface $em, EmailService $emailService, AuditService $auditService, int $id): Response
    {
        $reservation = $repo->find($id);
        
        if (!$reservation) {
            $this->addFlash('error', 'Réservation non trouvée.');
            return $this->redirectToRoute('app_reservation_resource_index');
        }
        
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), (string) $request->request->get('_token'))) {
            // Logger la suppression dans l'audit avant de supprimer l'entité
            $auditService->logDelete($reservation);
            
            $em->remove($reservation);
            $em->flush();
            
            // Envoyer la notification à l'administrateur
            $user = $this->getUser();
            $userName = 'Utilisateur';
            if ($user) {
                $userName = method_exists($user, 'getFullName') ? $user->getFullName() : 'Utilisateur';
            }
            
            $startTime = $reservation->getStartTime();
            $dateStr = $startTime?->format('d/m/Y') ?? 'date inconnue';
            
            $emailService->sendNotification(
                'admin@pidev.com', 
                'Réservation supprimée - ' . ($reservation->getEvent() ? $reservation->getEvent()->getTitle() : 'Réservation'),
                'La réservation a été supprimée par ' . $userName . ' pour le ' . $dateStr,
                ['user_name' => $userName, 'reservation' => $reservation]
            );
            
            $this->addFlash('success', 'Réservation supprimée avec succès !');
        }

        return $this->redirectToRoute('app_reservation_resource_index');
    }

    #[Route('/calendar', name: 'app_reservation_resource_calendar', methods: ['GET'])]
    public function calendar(): Response
    {
        return $this->render('resource/reservation_resource/calendar.html.twig', [
            'pageInfo' => [
                'title' => 'Calendrier des Réservations',
                'subtitle' => 'Visualisez et gérez les réservations de ressources'
            ]
        ]);
    }

    #[Route('/calendar/events', name: 'app_reservation_resource_calendar_events', methods: ['GET'])]
    public function getCalendarEvents(Request $request, ReservationResourceRepository $repo): JsonResponse
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');
        
        $startDate = new \DateTime((string) $start);
        $endDate = new \DateTime((string) $end);
        
        $reservations = $repo->createQueryBuilder('r')
            ->where('r.startTime >= :start')
            ->andWhere('r.endTime <= :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();
        
        $events = [];
        foreach ($reservations as $reservation) {
            $resourceName = '';
            if ($reservation->getResourceType() === 'SALLE' && $reservation->getSalle()) {
                $resourceName = 'Salle: ' . $reservation->getSalle()->getName();
            } elseif ($reservation->getResourceType() === 'EQUIPEMENT' && $reservation->getEquipement()) {
                $resourceName = 'Équipement: ' . $reservation->getEquipement()->getName() . ' (x' . $reservation->getQuantity() . ')';
            }
            
            $startTime = $reservation->getStartTime();
            $endTime = $reservation->getEndTime();
            $events[] = [
                'id' => $reservation->getId(),
                'title' => $resourceName . ' - ' . ($reservation->getEvent() ? $reservation->getEvent()->getTitle() : 'Événement'),
                'start' => $startTime?->format('Y-m-d') ?? '',
                'end' => $endTime?->format('Y-m-d') ?? '',
                'color' => '#dc3545', // Rouge pour les dates indisponibles
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'resourceType' => $reservation->getResourceType(),
                    'quantity' => $reservation->getQuantity(),
                    'eventId' => $reservation->getEvent() ? $reservation->getEvent()->getId() : null
                ]
            ];
        }
        
        return new JsonResponse($events);
    }

    #[Route('/export-pdf', name: 'app_reservation_resource_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, ReservationResourceRepository $repo, Environment $twig): Response
    {
        // Récupérer les filtres et le tri
        $filters = [];
        $name = $request->query->get('name');
        if ($name && is_string($name)) {
            $filters['name'] = $name;
        }
        $resourceType = $request->query->get('resourceType');
        if ($resourceType && is_string($resourceType)) {
            $filters['resourceType'] = $resourceType;
        }

        $sortBy = $request->query->get('sortBy', 'startTime');
        $direction = $request->query->get('direction', 'desc');

        $reservations = $repo->findByFilters(
            $filters,
            is_string($sortBy) ? $sortBy : 'startTime',
            is_string($direction) ? $direction : 'desc'
        );

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

    private function getActionLabel(string $action): string
    {
        return match($action) {
            'CREATE' => 'Création',
            'UPDATE' => 'Modification',
            'DELETE' => 'Suppression',
            default => $action
        };
    }

    private function getResourceTypeLabel(string $resourceType): string
    {
        return match($resourceType) {
            'RESERVATION' => 'Réservation',
            'SALLE' => 'Salle',
            'EQUIPEMENT' => 'Équipement',
            default => $resourceType
        };
    }

    /**
     * Extract changes from old and new values
     * @return array<string, array{old: mixed, new: mixed}>|null
     */
    private function getChanges(?string $oldValues, ?string $newValues): ?array
    {
        if ($oldValues || $newValues) {
            $old = $oldValues ? json_decode($oldValues, true) : [];
            $new = $newValues ? json_decode($newValues, true) : [];
            
            /** @var array<string, array{old: mixed, new: mixed}> $changes */
            $changes = [];
            foreach ($old as $key => $oldValue) {
                if (isset($new[$key]) && $oldValue !== $new[$key]) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $new[$key]
                    ];
                }
            }
            
            return $changes;
        }
        
        return null;
    }

    /**
     * Generate description from log data
     * @param array{user_name?: string, created_at: string|\DateTime, resource_name?: string, action?: string} $log
     */
    private function getDescription(array $log): string
    {
        /** @var array{user_name?: string, created_at: string|\DateTime, resource_name?: string, action?: string} $log */
        $userName = $log['user_name'] ?? 'Utilisateur inconnu';
        $resourceName = $log['resource_name'] ?? 'Ressource inconnue';
        $action = $log['action'] ?? 'UNKNOWN';
        
        // created_at peut être un objet DateTime ou une chaîne
        if ($log['created_at'] instanceof \DateTime) {
            $createdAt = $log['created_at'];
        } else {
            $createdAt = new \DateTime($log['created_at']);
        }
        $time = $createdAt->format('H:i');
        
        return sprintf(
            "%s %s par %s à %s",
            $resourceName,
            $this->getActionLabel($action),
            $userName,
            $time
        );
    }
}