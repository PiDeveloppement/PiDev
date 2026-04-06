<?php

namespace App\Controller\Sponsor;

use App\Entity\Sponsor\Sponsor;
use App\Entity\User\UserModel;
use App\Form\Sponsor\SponsorType;
use App\Repository\Sponsor\SponsorRepository;
use App\Service\Sponsor\SponsorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

class SponsorController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SponsorRepository $sponsorRepository,
        private SponsorService $sponsorService,
        private SluggerInterface $slugger
    ) {
    }

    #[Route('/admin/sponsors', name: 'app_sponsors_index', methods: ['GET'])]
    public function adminIndex(Request $request): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $search = trim((string) $request->query->get('q', ''));
        $company = trim((string) $request->query->get('company', ''));
        $eventId = (int) $request->query->get('event_id', 0);

        $sponsors = $this->sponsorRepository->searchForAdmin(
            $search !== '' ? $search : null,
            $company !== '' ? $company : null,
            $eventId > 0 ? $eventId : null
        );

        $events = $this->sponsorService->fetchEventsCatalog();
        $eventTitleMap = $this->sponsorService->buildEventTitleMapForSponsors($sponsors);

        return $this->render('sponsor/admin.html.twig', [
            'pageInfo' => [
                'title' => 'Liste des entreprises',
                'subtitle' => '',
            ],
            'sponsors' => $sponsors,
            'events' => $events,
            'eventTitleMap' => $eventTitleMap,
            'companies' => $this->sponsorRepository->getDistinctCompanies(),
            'search' => $search,
            'selectedCompany' => $company,
            'selectedEventId' => $eventId > 0 ? $eventId : null,
            'stats' => [
                'total' => $this->sponsorRepository->getTotalSponsors(),
                'totalContribution' => $this->sponsorRepository->getTotalContribution(),
                'averageContribution' => $this->sponsorRepository->getAverageContribution(),
                'byEvent' => $this->sponsorRepository->getContributionsByEvent(),
                'topCompanies' => $this->sponsorRepository->getTopCompaniesByContribution(),
            ],
        ]);
    }

    #[Route('/admin/sponsors/new', name: 'app_sponsors_new', methods: ['GET', 'POST'])]
    public function adminNew(Request $request): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $sponsor = new Sponsor();
        $form = $this->createForm(SponsorType::class, $sponsor, [
            'event_choices' => $this->sponsorService->buildEventChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($this->sponsorService->hasDuplicateSponsor($sponsor)) {
                    $this->addFlash('error', 'Une entreprise avec le meme email existe deja pour cet evenement.');
                } else {
                $this->sponsorService->hydrateSponsorUserRelation($sponsor);
                $this->handleSponsorUploads($request, $form, $sponsor);
                $this->entityManager->persist($sponsor);
                $this->entityManager->flush();

                $this->addFlash('success', 'Sponsor ajoute avec succes.');
                return $this->redirectToRoute('app_sponsors_index');
                }
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('sponsor/form.html.twig', [
            'pageInfo' => ['title' => 'Ajouter Entreprise Partenaire', 'subtitle' => ''],
            'form' => $form->createView(),
            'isEdit' => false,
            'backRoute' => 'app_sponsors_index',
        ]);
    }

    #[Route('/admin/sponsors/{id}/edit', name: 'app_sponsors_edit', methods: ['GET', 'POST'])]
    public function adminEdit(Request $request, Sponsor $sponsor): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $form = $this->createForm(SponsorType::class, $sponsor, [
            'event_choices' => $this->sponsorService->buildEventChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($this->sponsorService->hasDuplicateSponsor($sponsor, $sponsor->getId())) {
                    $this->addFlash('error', 'Une autre entreprise avec le meme email existe deja pour cet evenement.');
                } else {
                $this->sponsorService->hydrateSponsorUserRelation($sponsor);
                $this->handleSponsorUploads($request, $form, $sponsor);
                $this->entityManager->flush();

                $this->addFlash('success', 'Sponsor modifie avec succes.');
                return $this->redirectToRoute('app_sponsors_index');
                }
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('sponsor/form.html.twig', [
            'pageInfo' => ['title' => 'Ajouter Entreprise Partenaire', 'subtitle' => ''],
            'form' => $form->createView(),
            'isEdit' => true,
            'sponsor' => $sponsor,
            'backRoute' => 'app_sponsors_index',
        ]);
    }

    #[Route('/admin/sponsors/{id}/details', name: 'app_sponsors_details', methods: ['GET'])]
    public function adminDetails(Sponsor $sponsor): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $eventTitle = $this->sponsorService->getEventTitleById((int) $sponsor->getEventId()) ?? '-';

        return $this->render('sponsor/details.html.twig', [
            'pageInfo' => ['title' => 'Details sponsor', 'subtitle' => 'Recapitulatif complet'],
            'sponsor' => $sponsor,
            'eventTitle' => $eventTitle,
            'event' => $this->sponsorService->findEventById((int) $sponsor->getEventId()),
            'contractPreviewUrl' => $this->generateUrl('app_sponsors_contract_view', ['id' => $sponsor->getId()]),
            'contractDownloadUrl' => $this->generateUrl('app_sponsors_contract', ['id' => $sponsor->getId()]),
            'backRoute' => 'app_sponsors_index',
        ]);
    }

    #[Route('/admin/sponsors/{id}/contract', name: 'app_sponsors_contract', methods: ['GET'])]
    public function adminContract(Sponsor $sponsor): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        return $this->createSponsorContractResponse(
            $sponsor,
            false,
            'app_sponsors_details',
            ['id' => $sponsor->getId()]
        );
    }

    #[Route('/admin/sponsors/{id}/contract/view', name: 'app_sponsors_contract_view', methods: ['GET'])]
    public function adminContractView(Sponsor $sponsor): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        return $this->createSponsorContractResponse(
            $sponsor,
            true,
            'app_sponsors_details',
            ['id' => $sponsor->getId()]
        );
    }

    #[Route('/admin/sponsors/{id}', name: 'app_sponsors_delete', methods: ['POST'])]
    public function adminDelete(Request $request, Sponsor $sponsor): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        if ($this->isCsrfTokenValid('delete_sponsor_' . $sponsor->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($sponsor);
            $this->entityManager->flush();
            $this->addFlash('success', 'Sponsor supprime.');
        }

        return $this->redirectToRoute('app_sponsors_index');
    }

    #[Route('/admin/sponsors/export/csv', name: 'app_sponsors_export_csv', methods: ['GET'])]
    public function adminExportCsv(Request $request): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $search = trim((string) $request->query->get('q', ''));
        $company = trim((string) $request->query->get('company', ''));
        $eventId = (int) $request->query->get('event_id', 0);

        $items = $this->sponsorRepository->searchForAdmin(
            $search !== '' ? $search : null,
            $company !== '' ? $company : null,
            $eventId > 0 ? $eventId : null
        );

        return $this->buildCsvResponse($items, 'sponsors_admin_export.csv');
    }

    #[Route('/sponsor/portal', name: 'app_sponsor_portal', methods: ['GET'])]
    public function portal(Request $request): Response
    {
        $user = $this->getUser();
        $isSponsorSession = $user instanceof UserModel && $this->sponsorService->isSponsorUser($user);
        $email = $isSponsorSession ? (string) $user->getEmail() : '';
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'date_asc');
        $mySort = (string) $request->query->get('my_sort', 'recent');

        $mySponsors = $isSponsorSession
            ? $this->sponsorRepository->findByContactEmailWithEvent($email)
            : [];
        $allEvents = $this->sponsorService->fetchEventsCatalog();
        $recommendedEvents = $isSponsorSession
            ? $this->sponsorService->buildRecommendedEvents($allEvents, $user, $email)
            : array_slice($allEvents, 0, 6);

        if ($query !== '') {
            $needle = mb_strtolower($query);
            $mySponsors = array_values(array_filter($mySponsors, function (Sponsor $sponsor) use ($needle): bool {
                $eventTitle = (string) $this->sponsorService->getEventTitleById((int) $sponsor->getEventId());
                return str_contains(mb_strtolower($eventTitle), $needle);
            }));

            $allEvents = array_values(array_filter($allEvents, static function (array $event) use ($needle): bool {
                return str_contains(mb_strtolower((string) ($event['title'] ?? '')), $needle);
            }));

            $recommendedEvents = array_values(array_filter($recommendedEvents, static function (array $event) use ($needle): bool {
                return str_contains(mb_strtolower((string) ($event['title'] ?? '')), $needle);
            }));
        }

        $allEvents = $this->sponsorService->sortEvents($allEvents, $sort);
        $recommendedEvents = $this->sponsorService->sortEvents($recommendedEvents, $sort);
        $mySponsors = $this->sponsorService->sortSponsors($mySponsors, $mySort);

        $eventBuckets = $this->sponsorService->splitEventsByStatus($allEvents);
        $sponsorableEvents = $this->sponsorService->sortEvents($eventBuckets['sponsorable'], $sort);
        $archivedEvents = $this->sponsorService->sortEvents($eventBuckets['archived'], $sort);
        $recommendedEvents = $this->sponsorService->sortEvents(array_values(array_filter(
            $recommendedEvents,
            fn (array $event): bool => $this->sponsorService->resolveEventStatus($event['startDate'] ?? null, $event['endDate'] ?? null)['key'] !== 'termine'
        )), $sort);
        $historyItems = $this->sponsorService->buildSponsorHistory($mySponsors);

        $eventTypeStats = $this->sponsorService->buildEventTypeStats($allEvents);
        $eventMonthStats = $this->sponsorService->buildEventMonthStats($allEvents, 6);
        $contributionsByEvent = $isSponsorSession
            ? $this->sponsorRepository->getMyContributionsByEvent($email)
            : [];

        return $this->render('sponsor/portal_home.html.twig', [
            'pageInfo' => ['title' => 'Portail Sponsoring', 'subtitle' => 'Evenements a sponsoriser'],
            'search' => $query,
            'sort' => $sort,
            'mySort' => $mySort,
            'isSponsorSession' => $isSponsorSession,
            'mySponsors' => $mySponsors,
            'mySponsorEventTitleMap' => $this->sponsorService->buildEventTitleMapForSponsors($mySponsors),
            'recommendedEvents' => $recommendedEvents,
            'allEvents' => $allEvents,
            'sponsorableEvents' => $sponsorableEvents,
            'eventCounts' => [
                'total' => count($allEvents),
                'sponsorable' => count($sponsorableEvents),
                'ongoing' => $eventBuckets['ongoingCount'],
            ],
            'stats' => $isSponsorSession
                ? $this->sponsorRepository->getMyStats($email)
                : [
                    'count' => 0,
                    'total' => 0.0,
                    'events' => 0,
                ],
            'contributionsByEvent' => $contributionsByEvent,
            'typeBars' => $this->sponsorService->toBarRows($eventTypeStats, 6),
            'monthBars' => $this->sponsorService->toBarRows($eventMonthStats, 6),
            'contributionBars' => $this->sponsorService->toBarRows($contributionsByEvent, 6),
        ]);
    }

    #[Route('/sponsor/portal/history', name: 'app_sponsor_portal_history', methods: ['GET'])]
    public function portalHistory(Request $request): Response
    {
        $user = $this->getUser();
        $isSponsorSession = $user instanceof UserModel && $this->sponsorService->isSponsorUser($user);
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'recent');
        
        $historyItems = [];
        $stats = [
            'count' => 0,
            'total' => 0.0,
            'ongoing' => 0,
            'ended' => 0,
        ];

        if ($isSponsorSession) {
            $email = (string) $user->getEmail();

            $mySponsors = $this->sponsorRepository->findByContactEmailWithEvent($email);
            $historyItems = $this->sponsorService->buildSponsorHistory($mySponsors);

            if ($query !== '') {
                $needle = mb_strtolower($query);
                $historyItems = array_values(array_filter($historyItems, static function (array $item) use ($needle): bool {
                    return str_contains(mb_strtolower((string) ($item['eventTitle'] ?? '')), $needle);
                }));
            }

            usort($historyItems, static function (array $a, array $b) use ($sort): int {
                $aTime = $a['startDate'] instanceof \DateTimeInterface ? $a['startDate']->getTimestamp() : 0;
                $bTime = $b['startDate'] instanceof \DateTimeInterface ? $b['startDate']->getTimestamp() : 0;

                return match ($sort) {
                    'oldest' => $aTime <=> $bTime,
                    'amount_desc' => ((float) $b['contribution']) <=> ((float) $a['contribution']),
                    'amount_asc' => ((float) $a['contribution']) <=> ((float) $b['contribution']),
                    default => $bTime <=> $aTime,
                };
            });

            $stats = [
                'count' => count($historyItems),
                'total' => array_reduce($historyItems, static fn (float $sum, array $item): float => $sum + (float) $item['contribution'], 0.0),
                'ongoing' => count(array_filter($historyItems, static fn (array $item): bool => ($item['statusKey'] ?? '') === 'en_cours')),
                'ended' => count(array_filter($historyItems, static fn (array $item): bool => ($item['statusKey'] ?? '') === 'termine')),
            ];
        }

        return $this->render('sponsor/portal_history.html.twig', [
            'isSponsorSession' => $isSponsorSession,
            'historyItems' => $historyItems,
            'search' => $query,
            'sort' => $sort,
            'stats' => $stats,
        ]);
    }

    #[Route('/sponsor/portal/event/{id}', name: 'app_sponsor_portal_event', methods: ['GET'])]
    public function portalEventDetails(int $id): Response
    {
        $event = $this->sponsorService->findEventById($id);
        if ($event === null) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        $status = $this->sponsorService->resolveEventStatus($event['startDate'] ?? null, $event['endDate'] ?? null);

        return $this->render('sponsor/portal_event.html.twig', [
            'event' => $event,
            'status' => $status,
        ]);
    }

    #[Route('/sponsor/portal/sponsoriser/{id}', name: 'app_sponsor_portal_sponsorize', methods: ['GET', 'POST'])]
    public function portalSponsorize(Request $request, int $id): Response
    {
        $user = $this->getUser();
        $fixedEmail = $user instanceof UserModel ? trim((string) $user->getEmail()) : null;

        $event = $this->sponsorService->findEventById($id);
        if ($event === null) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        $activeEvents = $this->sponsorService->fetchActiveEvents();
        $sponsor = new Sponsor();
        $sponsor->setEventId($id);
        if ($fixedEmail !== null && $fixedEmail !== '') {
            $sponsor->setContactEmail($fixedEmail);
        }
        if ($user instanceof UserModel) {
            $sponsor->setUser($user);
        }

        $form = $this->createForm(SponsorType::class, $sponsor, [
            'fixed_email' => $fixedEmail,
            'fixed_event_id' => null,
            'event_choices' => $this->sponsorService->buildEventChoices($activeEvents),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedEventId = (int) $sponsor->getEventId();
            if ($user instanceof UserModel) {
                $sponsor->setContactEmail((string) $user->getEmail());
                $sponsor->setUser($user);
            } else {
                $sponsor->setUser(null);
            }

            $duplicate = $this->sponsorRepository->findOneBy([
                'eventId' => $selectedEventId,
                'contactEmail' => $sponsor->getContactEmail(),
                'companyName' => $sponsor->getCompanyName(),
            ]);

            if ($duplicate instanceof Sponsor) {
                $this->addFlash('error', 'Cette entreprise a deja sponsorise cet evenement avec le meme email.');
            } else {
                try {
                    $this->handleSponsorUploads($request, $form, $sponsor);
                    $this->entityManager->persist($sponsor);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Sponsoring enregistre avec succes.');

                    return $this->redirectToRoute('app_sponsor_portal');
                } catch (\RuntimeException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        return $this->render('sponsor/portal_form.html.twig', [
            'pageInfo' => ['title' => 'Sponsoriser un evenement', 'subtitle' => (string) ($event['title'] ?? '')],
            'form' => $form->createView(),
            'isEdit' => false,
            'backRoute' => 'app_sponsor_portal',
        ]);
    }

    #[Route('/sponsor/portal/{id}/edit', name: 'app_sponsor_portal_edit', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function portalEdit(Request $request, Sponsor $sponsor): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel || !$this->sponsorService->canCurrentUserManageSponsor($user, $sponsor)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce sponsor.');
        }

        $event = $this->sponsorService->findEventById((int) $sponsor->getEventId());

        $form = $this->createForm(SponsorType::class, $sponsor, [
            'fixed_email' => $user->getEmail(),
            'fixed_event_id' => $sponsor->getEventId(),
            'event_choices' => $this->sponsorService->buildEventChoices($event ? [$event] : []),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sponsor->setContactEmail((string) $user->getEmail());
            $sponsor->setUser($user);
            try {
                $this->handleSponsorUploads($request, $form, $sponsor);
                $this->entityManager->flush();

                $this->addFlash('success', 'Sponsoring modifie avec succes.');
                return $this->redirectToRoute('app_sponsor_portal');
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('sponsor/portal_form.html.twig', [
            'pageInfo' => ['title' => 'Modifier sponsoring', 'subtitle' => 'Mise a jour'],
            'form' => $form->createView(),
            'isEdit' => true,
            'sponsor' => $sponsor,
            'backRoute' => 'app_sponsor_portal',
        ]);
    }

    #[Route('/sponsor/portal/{id}/details', name: 'app_sponsor_portal_details', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function portalDetails(Sponsor $sponsor): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel || !$this->sponsorService->canCurrentUserManageSponsor($user, $sponsor)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir ce sponsor.');
        }

        return $this->render('sponsor/portal_details.html.twig', [
            'pageInfo' => ['title' => 'Details sponsor', 'subtitle' => 'Votre sponsoring'],
            'sponsor' => $sponsor,
            'eventTitle' => $this->sponsorService->getEventTitleById((int) $sponsor->getEventId()) ?? '-',
            'event' => $this->sponsorService->findEventById((int) $sponsor->getEventId()),
            'contractPreviewUrl' => $this->generateUrl('app_sponsor_portal_contract_view', ['id' => $sponsor->getId()]),
            'contractDownloadUrl' => $this->generateUrl('app_sponsor_portal_contract', ['id' => $sponsor->getId()]),
            'backRoute' => 'app_sponsor_portal',
        ]);
    }

    #[Route('/sponsor/portal/{id}/contract', name: 'app_sponsor_portal_contract', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function portalContract(Sponsor $sponsor): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel || !$this->sponsorService->canCurrentUserManageSponsor($user, $sponsor)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir ce contrat.');
        }

        return $this->createSponsorContractResponse(
            $sponsor,
            false,
            'app_sponsor_portal_details',
            ['id' => $sponsor->getId()]
        );
    }

    #[Route('/sponsor/portal/{id}/contract/view', name: 'app_sponsor_portal_contract_view', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function portalContractView(Sponsor $sponsor): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel || !$this->sponsorService->canCurrentUserManageSponsor($user, $sponsor)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir ce contrat.');
        }

        return $this->createSponsorContractResponse(
            $sponsor,
            true,
            'app_sponsor_portal_details',
            ['id' => $sponsor->getId()]
        );
    }

    #[Route('/sponsor/portal/{id}/delete', name: 'app_sponsor_portal_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function portalDelete(Request $request, Sponsor $sponsor): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel || !$this->sponsorService->canCurrentUserManageSponsor($user, $sponsor)) {
            throw $this->createAccessDeniedException('Suppression non autorisee.');
        }

        if ($this->isCsrfTokenValid('delete_sponsor_' . $sponsor->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($sponsor);
            $this->entityManager->flush();
            $this->addFlash('success', 'Sponsoring supprime.');
        }

        return $this->redirectToRoute('app_sponsor_portal');
    }

    #[Route('/sponsor/portal/export/csv', name: 'app_sponsor_portal_export_csv', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function portalExportCsv(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            return $this->redirectToRoute('app_login');
        }
        if (!$this->sponsorService->isSponsorUser($user)) {
            throw $this->createAccessDeniedException('Acces reserve au sponsor.');
        }

        $items = $this->sponsorRepository->findByContactEmailWithEvent((string) $user->getEmail());
        return $this->buildCsvResponse($items, 'mes_sponsors_export.csv');
    }
    private function handleSponsorUploads(Request $request, FormInterface $form, Sponsor $sponsor): void
    {
        $logoFile = $form->has('logoFile') ? $form->get('logoFile')->getData() : null;
        if ($logoFile instanceof UploadedFile) {
            $sponsor->setLogoUrl($this->storeUploadedFile($request, $logoFile, 'logos'));
        }

        $contractFile = $form->has('contractFile') ? $form->get('contractFile')->getData() : null;
        if ($contractFile instanceof UploadedFile) {
            $sponsor->setContractUrl($this->storeUploadedFile($request, $contractFile, 'contracts'));
        }
    }

    private function storeUploadedFile(Request $request, UploadedFile $file, string $bucket): string
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $targetDir = $projectDir . '/public/uploads/sponsor/' . $bucket;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Impossible de creer le dossier d\'upload.');
        }

        $base = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBase = (string) $this->slugger->slug($base !== '' ? $base : 'fichier');
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = $safeBase . '-' . bin2hex(random_bytes(4)) . '.' . $extension;

        try {
            $file->move($targetDir, $filename);
        } catch (FileException $e) {
            throw new \RuntimeException('Echec de l\'upload du fichier.', 0, $e);
        }

        $publicPath = '/uploads/sponsor/' . $bucket . '/' . $filename;
        return rtrim($request->getSchemeAndHttpHost(), '/') . $publicPath;
    }

    private function createSponsorContractResponse(Sponsor $sponsor, bool $inline, string $backRoute, array $backRouteParams = []): Response
    {
        $event = $this->sponsorService->findEventById((int) $sponsor->getEventId());
        $pdf = $this->buildSponsorContractPdf($sponsor, $event);
        $filename = 'contrat-sponsor-' . $this->slugifyFilename((string) ($sponsor->getCompanyName() ?? 'eventflow')) . '.pdf';

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            sprintf('%s; filename="%s"', $inline ? 'inline' : 'attachment', $filename)
        );

        return $response;
    }

    /**
     * @param array{id:int,title:string,description:string,location:string,startDate:?\DateTimeImmutable,endDate:?\DateTimeImmutable}|null $event
     */
    private function buildSponsorContractPdf(Sponsor $sponsor, ?array $event): string
    {
        $eventTitle = $event['title'] ?? ($this->sponsorService->getEventTitleById((int) $sponsor->getEventId()) ?? '-');
        $eventflowLogo = $this->createPdfImageObject(
            (string) $this->getParameter('kernel.project_dir') . '/public/images/logo.png',
            'ImEventFlow',
            60,
            60
        );
        $sponsorLogo = $this->createPdfImageObject(
            $this->resolveLocalPublicFileFromUrl($sponsor->getLogoUrl()),
            'ImSponsor',
            82,
            68
        );
        $lines = [];
        $images = [];
        $y = 800;

        if ($eventflowLogo !== null) {
            $images[] = $eventflowLogo;
            $lines[] = $this->pdfDrawImage('ImEventFlow', 52, 752, $eventflowLogo['drawWidth'], $eventflowLogo['drawHeight']);
        }
        if ($sponsorLogo !== null) {
            $images[] = $sponsorLogo;
            $lines[] = $this->pdfDrawImage('ImSponsor', 450, 752, $sponsorLogo['drawWidth'], $sponsorLogo['drawHeight']);
        }

        $lines[] = $this->pdfText('EventFlow', 120, $y - 2, 15, true);
        $lines[] = $this->pdfText('CONTRAT SPONSOR', 120, $y - 24, 24, true);
        if ($sponsorLogo === null) {
            $lines[] = $this->pdfText((string) ($sponsor->getCompanyName() ?? 'Sponsor'), 420, $y - 8, 18, true);
        }
        $lines[] = "52 724 m 543 724 l S";
        $y = 698;

        $meta = [
            'Date: ' . (new \DateTimeImmutable())->format('d/m/Y'),
            'Evenement: ' . $eventTitle,
        ];
        if (!empty($event['location'])) {
            $meta[] = 'Lieu: ' . (string) $event['location'];
        }
        if (($event['startDate'] ?? null) instanceof \DateTimeInterface) {
            $meta[] = 'Debut: ' . $event['startDate']->format('d/m/Y H:i');
        }

        foreach ($meta as $metaLine) {
            $lines[] = $this->pdfText($metaLine, 52, $y, 12, false);
            $y -= 18;
        }

        $y -= 10;
        $lines[] = $this->pdfText('Informations:', 52, $y, 16, true);
        $y -= 24;

        $info = [
            'Entreprise: ' . (string) ($sponsor->getCompanyName() ?? '-'),
            'Email: ' . (string) ($sponsor->getContactEmail() ?? '-'),
            'Contribution: ' . number_format($sponsor->getContributionAmount(), 2, ',', ' ') . ' DT',
            'Numero fiscal: ' . (string) ($sponsor->getTaxId() ?? '-'),
            'Secteur: ' . (string) ($sponsor->getIndustry() ?? '-'),
            'Telephone: ' . (string) ($sponsor->getPhone() ?? '-'),
        ];

        foreach ($info as $infoLine) {
            $lines[] = $this->pdfText($infoLine, 52, $y, 12, false);
            $y -= 18;
        }

        $y -= 12;
        $lines[] = $this->pdfText('Contrat Sponsor:', 52, $y, 16, true);
        $y -= 24;

        $paragraphs = [
            "Le sponsor confirme sa contribution a l'evenement mentionne ci-dessus.",
            "La contribution sera utilisee pour les besoins organisationnels, la communication et les ressources liees a cet evenement.",
            "Ce document resume l'accord de sponsoring valide entre l'organisateur et le sponsor.",
        ];

        foreach ($paragraphs as $paragraph) {
            foreach ($this->wrapPdfText($paragraph, 82) as $wrappedLine) {
                $lines[] = $this->pdfText($wrappedLine, 52, $y, 12, false);
                $y -= 16;
            }
            $y -= 6;
        }

        $y -= 14;
        $lines[] = $this->pdfText('Signature Organisateur:', 52, $y, 12, true);
        $lines[] = $this->pdfText('Signature Sponsor:', 340, $y, 12, true);
        $y -= 20;
        $lines[] = "52 {$y} m 250 {$y} l S";
        $lines[] = "340 {$y} m 538 {$y} l S";

        $stream = "0 0 0 rg\n";
        $stream .= "0.25 w\n";
        $stream .= implode("\n", $lines) . "\n";

        return $this->buildSinglePagePdf($stream, $images);
    }

    /**
     * @param array<int,array{name:string,width:int,height:int,drawWidth:float,drawHeight:float,data:string,filter:string,colorSpace:string,bits:int,decodeParms:?string,smaskData:?string,smaskDecodeParms:?string}> $images
     */
    private function buildSinglePagePdf(string $content, array $images = []): string
    {
        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>",
        ];

        $nextId = 6;
        $xObjectParts = [];
        foreach ($images as $image) {
            $smaskId = null;
            if (!empty($image['smaskData'])) {
                $smaskId = $nextId++;
                $smaskDecode = $image['smaskDecodeParms'] ? ' /DecodeParms ' . $image['smaskDecodeParms'] : '';
                $objects[$smaskId] = "<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode{$smaskDecode} /Length " . strlen((string) $image['smaskData']) . " >>\nstream\n" . $image['smaskData'] . "endstream";
            }

            $decodeParms = $image['decodeParms'] ? ' /DecodeParms ' . $image['decodeParms'] : '';
            $smask = $smaskId !== null ? ' /SMask ' . $smaskId . ' 0 R' : '';
            $objects[$nextId] = "<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace {$image['colorSpace']} /BitsPerComponent {$image['bits']} /Filter /{$image['filter']}{$decodeParms}{$smask} /Length " . strlen($image['data']) . " >>\nstream\n" . $image['data'] . "endstream";
            $xObjectParts[] = '/' . $image['name'] . ' ' . $nextId . ' 0 R';
            ++$nextId;
        }

        $contentId = $nextId;
        $xObjectDict = $xObjectParts === [] ? '' : ' /XObject << ' . implode(' ', $xObjectParts) . ' >>';
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >>{$xObjectDict} >> /Contents {$contentId} 0 R >>";
        $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text, int $x, int $y, int $size, bool $bold): string
    {
        $font = $bold ? 'F2' : 'F1';
        $safe = $this->escapePdfText($text);

        return sprintf("BT /%s %d Tf 1 0 0 1 %d %d Tm (%s) Tj ET", $font, $size, $x, $y, $safe);
    }

    private function pdfDrawImage(string $name, float $x, float $y, float $width, float $height): string
    {
        return sprintf(
            "q %s 0 0 %s %s %s cm /%s Do Q",
            number_format($width, 2, '.', ''),
            number_format($height, 2, '.', ''),
            number_format($x, 2, '.', ''),
            number_format($y, 2, '.', ''),
            $name
        );
    }

    private function escapePdfText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $ascii = $ascii === false ? $text : $ascii;
        $ascii = str_replace(["\r", "\n"], ' ', $ascii);

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $ascii
        );
    }

    /**
     * @return string[]
     */
    private function wrapPdfText(string $text, int $maxChars): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (strlen($candidate) > $maxChars) {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function slugifyFilename(string $value): string
    {
        $safe = (string) $this->slugger->slug($value);
        return $safe !== '' ? strtolower($safe) : 'document';
    }

    private function resolveLocalPublicFileFromUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $publicDir = realpath($projectDir . '/public');
        $candidate = realpath($projectDir . '/public' . $path);

        if ($publicDir === false || $candidate === false || !str_starts_with($candidate, $publicDir) || !is_file($candidate)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return array{name:string,width:int,height:int,drawWidth:float,drawHeight:float,data:string,filter:string,colorSpace:string,bits:int,decodeParms:?string,smaskData:?string,smaskDecodeParms:?string}|null
     */
    private function createPdfImageObject(?string $path, string $name, float $maxWidth, float $maxHeight): ?array
    {
        if ($path === null || !is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $size = @getimagesize($path);
        if (!is_array($size) || empty($size[0]) || empty($size[1])) {
            return null;
        }

        $width = (int) $size[0];
        $height = (int) $size[1];
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1.0);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return [
                'name' => $name,
                'width' => $width,
                'height' => $height,
                'drawWidth' => $width * $ratio,
                'drawHeight' => $height * $ratio,
                'data' => $raw,
                'filter' => 'DCTDecode',
                'colorSpace' => '/DeviceRGB',
                'bits' => 8,
                'decodeParms' => null,
                'smaskData' => null,
                'smaskDecodeParms' => null,
            ];
        }

        if ($extension !== 'png' || !function_exists('gzuncompress') || !function_exists('gzcompress')) {
            return null;
        }

        return $this->createPdfPngImageObject($raw, $name, $width, $height, $ratio);
    }

    /**
     * @return array{name:string,width:int,height:int,drawWidth:float,drawHeight:float,data:string,filter:string,colorSpace:string,bits:int,decodeParms:?string,smaskData:?string,smaskDecodeParms:?string}|null
     */
    private function createPdfPngImageObject(string $raw, string $name, int $width, int $height, float $ratio): ?array
    {
        $signature = substr($raw, 0, 8);
        if ($signature !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $offset = 8;
        $colorType = null;
        $bitDepth = null;
        $idat = '';
        $palette = null;
        $transparency = null;

        while ($offset + 8 <= strlen($raw)) {
            $length = unpack('N', substr($raw, $offset, 4))[1];
            $type = substr($raw, $offset + 4, 4);
            $data = substr($raw, $offset + 8, $length);
            $offset += 12 + $length;

            if ($type === 'IHDR') {
                $ihdr = unpack('Nwidth/Nheight/Cbit/Ccolor/Ccompression/Cfilter/Cinterlace', $data);
                $bitDepth = (int) $ihdr['bit'];
                $colorType = (int) $ihdr['color'];
            } elseif ($type === 'PLTE') {
                $palette = $data;
            } elseif ($type === 'tRNS') {
                $transparency = $data;
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }

        if ($idat === '' || !in_array($colorType, [2, 3, 6], true)) {
            return null;
        }

        if ($colorType === 3) {
            return $this->createIndexedPdfPngImageObject($name, $width, $height, $ratio, $bitDepth ?? 8, $idat, $palette, $transparency);
        }

        if ($bitDepth !== 8) {
            return null;
        }

        $decodeParmsRgb = '<< /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns ' . $width . ' >>';

        if ($colorType === 2) {
            return [
                'name' => $name,
                'width' => $width,
                'height' => $height,
                'drawWidth' => $width * $ratio,
                'drawHeight' => $height * $ratio,
                'data' => $idat,
                'filter' => 'FlateDecode',
                'colorSpace' => '/DeviceRGB',
                'bits' => 8,
                'decodeParms' => $decodeParmsRgb,
                'smaskData' => null,
                'smaskDecodeParms' => null,
            ];
        }

        $decoded = @gzuncompress($idat);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $rowBytes = 1 + ($width * 4);
        $rgb = '';
        $alpha = '';

        for ($row = 0; $row < $height; ++$row) {
            $rowData = substr($decoded, $row * $rowBytes, $rowBytes);
            if (strlen($rowData) !== $rowBytes) {
                return null;
            }

            $filter = $rowData[0];
            $rgb .= $filter;
            $alpha .= $filter;

            for ($x = 0; $x < $width; ++$x) {
                $base = 1 + ($x * 4);
                $rgb .= $rowData[$base] . $rowData[$base + 1] . $rowData[$base + 2];
                $alpha .= $rowData[$base + 3];
            }
        }

        $rgbCompressed = gzcompress($rgb);
        $alphaCompressed = gzcompress($alpha);
        if (!is_string($rgbCompressed) || !is_string($alphaCompressed)) {
            return null;
        }

        return [
            'name' => $name,
            'width' => $width,
            'height' => $height,
            'drawWidth' => $width * $ratio,
            'drawHeight' => $height * $ratio,
            'data' => $rgbCompressed,
            'filter' => 'FlateDecode',
            'colorSpace' => '/DeviceRGB',
            'bits' => 8,
            'decodeParms' => $decodeParmsRgb,
            'smaskData' => $alphaCompressed,
            'smaskDecodeParms' => '<< /Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $width . ' >>',
        ];
    }

    /**
     * @return array{name:string,width:int,height:int,drawWidth:float,drawHeight:float,data:string,filter:string,colorSpace:string,bits:int,decodeParms:?string,smaskData:?string,smaskDecodeParms:?string}|null
     */
    private function createIndexedPdfPngImageObject(string $name, int $width, int $height, float $ratio, int $bitDepth, string $idat, ?string $palette, ?string $transparency): ?array
    {
        if ($palette === null || $palette === '' || !in_array($bitDepth, [1, 2, 4, 8], true)) {
            return null;
        }

        $paletteHex = strtoupper(bin2hex($palette));
        $maxIndex = intdiv(strlen($palette), 3) - 1;
        $decodeParms = '<< /Predictor 15 /Colors 1 /BitsPerComponent ' . $bitDepth . ' /Columns ' . $width . ' >>';
        $smaskData = null;
        $smaskDecodeParms = null;

        if ($transparency !== null && $transparency !== '' && $bitDepth === 8) {
            $decoded = @gzuncompress($idat);
            if (!is_string($decoded) || $decoded === '') {
                return null;
            }

            $rowBytes = 1 + $width;
            $alphaStream = '';
            $alphaMap = array_values(unpack('C*', $transparency));

            for ($row = 0; $row < $height; ++$row) {
                $rowData = substr($decoded, $row * $rowBytes, $rowBytes);
                if (strlen($rowData) !== $rowBytes) {
                    return null;
                }

                $alphaStream .= $rowData[0];
                for ($x = 0; $x < $width; ++$x) {
                    $index = ord($rowData[$x + 1]);
                    $alphaStream .= chr($alphaMap[$index] ?? 255);
                }
            }

            $compressedAlpha = gzcompress($alphaStream);
            if (!is_string($compressedAlpha)) {
                return null;
            }

            $smaskData = $compressedAlpha;
            $smaskDecodeParms = '<< /Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $width . ' >>';
        }

        return [
            'name' => $name,
            'width' => $width,
            'height' => $height,
            'drawWidth' => $width * $ratio,
            'drawHeight' => $height * $ratio,
            'data' => $idat,
            'filter' => 'FlateDecode',
            'colorSpace' => '[/Indexed /DeviceRGB ' . $maxIndex . ' <' . $paletteHex . '>]',
            'bits' => $bitDepth,
            'decodeParms' => $decodeParms,
            'smaskData' => $smaskData,
            'smaskDecodeParms' => $smaskDecodeParms,
        ];
    }
    /**
     * @param Sponsor[] $items
     */
    private function buildCsvResponse(array $items, string $filename): Response
    {
        $eventTitleMap = $this->sponsorService->buildEventTitleMapForSponsors($items);

        $response = new StreamedResponse(function () use ($items, $eventTitleMap): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['id', 'event_id', 'event_title', 'company_name', 'contact_email', 'contribution_tnd', 'industry', 'phone', 'tax_id'], ';');

            foreach ($items as $sponsor) {
                $eventId = (int) ($sponsor->getEventId() ?? 0);
                fputcsv($out, [
                    $sponsor->getId(),
                    $eventId,
                    $eventTitleMap[$eventId] ?? '-',
                    $sponsor->getCompanyName(),
                    $sponsor->getContactEmail(),
                    number_format($sponsor->getContributionAmount(), 2, '.', ''),
                    $sponsor->getIndustry(),
                    $sponsor->getPhone(),
                    $sponsor->getTaxId(),
                ], ';');
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    private function denyUnlessAdminOrOrganizer(): void
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            throw $this->createAccessDeniedException('Acces reserve a l administration.');
        }

        $roleName = mb_strtolower((string) ($user->getRole()?->getRoleName() ?? ''));
        $roleId = (int) ($user->getRoleId() ?? 0);

        if (in_array($roleName, ['admin', 'organisateur'], true) || in_array($roleId, [4, 2], true)) {
            return;
        }

        throw $this->createAccessDeniedException('Acces reserve a l administration.');
    }
}
