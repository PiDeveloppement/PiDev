<?php

namespace App\Controller\Sponsor;

use App\Entity\Sponsor\Sponsor;
use App\Entity\User\UserModel;
use App\Form\Sponsor\SponsorType;
use App\Repository\Sponsor\SponsorRepository;
use App\Service\Currency\CurrencyConverterService;
use App\Service\Sponsor\SponsorAlertEmailService;
use App\Service\Sponsor\SponsorService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class SponsorController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SponsorRepository $sponsorRepository,
        private CurrencyConverterService $currencyConverter,
        private SponsorService $sponsorService,
        private SponsorAlertEmailService $sponsorAlertEmailService,
        private MailerInterface $mailer,
        private Pdf $pdfGenerator,
        private SluggerInterface $slugger,
        private ChartBuilderInterface $chartBuilder
    ) {
    }

    #[Route('/admin/sponsors', name: 'app_sponsors_index', methods: ['GET'])]
    public function adminIndex(Request $request): Response
    {
        $this->denyUnlessAdmin();

        // 1) Lire les filtres de la liste sponsors depuis l'URL.
        $search = trim((string) $request->query->get('q', ''));
        $company = trim((string) $request->query->get('company', ''));
        $eventId = (int) $request->query->get('event_id', 0);
        $sort = (string) $request->query->get('sort', 'default');

        // 2) Charger les sponsors correspondant aux filtres admin.
        $sponsors = $this->sponsorRepository->searchForAdmin(
            $search !== '' ? $search : null,
            $company !== '' ? $company : null,
            $eventId > 0 ? $eventId : null
        );

        // 3) Appliquer le tri cote serveur avant de construire les KPI.
        usort($sponsors, function (Sponsor $a, Sponsor $b) use ($sort): int {
            switch ($sort) {
                case 'name-asc':
                    return strcasecmp((string) $a->getCompanyName(), (string) $b->getCompanyName());
                case 'name-desc':
                    return strcasecmp((string) $b->getCompanyName(), (string) $a->getCompanyName());
                case 'contribution-asc':
                    return ((float) $a->getContributionAmount()) <=> ((float) $b->getContributionAmount());
                case 'contribution-desc':
                    return ((float) $b->getContributionAmount()) <=> ((float) $a->getContributionAmount());
                case 'date-asc':
                    return ((int) ($a->getId() ?? 0)) <=> ((int) ($b->getId() ?? 0));
                case 'date-desc':
                    return ((int) ($b->getId() ?? 0)) <=> ((int) ($a->getId() ?? 0));
                default:
                    return 0;
            }
        });

        $events = $this->sponsorService->fetchEventsCatalog();
        $eventTitleMap = $this->sponsorService->buildEventTitleMapForSponsors($sponsors);

        // 4) Regrouper les lignes sponsor par entreprise pour afficher une seule carte synthese.
        $companyCardsMap = [];
        foreach ($sponsors as $sponsor) {
            $companyName = trim((string) ($sponsor->getCompanyName() ?: '-'));
            $companyKey = mb_strtolower($companyName);

            if (!isset($companyCardsMap[$companyKey])) {
                $companyCardsMap[$companyKey] = [
                    'companyName' => $companyName,
                    'contactEmail' => (string) ($sponsor->getContactEmail() ?: '-'),
                    'logoUrl' => (string) ($sponsor->getLogoUrl() ?: ''),
                    'contributionTotal' => 0.0,
                    'contributionsCount' => 0,
                    'eventIds' => [],
                    'representativeSponsorId' => (int) ($sponsor->getId() ?? 0),
                ];
            }

            $companyCardsMap[$companyKey]['contributionTotal'] += (float) $sponsor->getContributionAmount();
            $companyCardsMap[$companyKey]['contributionsCount']++;

            $eventId = (int) $sponsor->getEventId();
            if ($eventId > 0) {
                $companyCardsMap[$companyKey]['eventIds'][$eventId] = true;
            }

            if ($companyCardsMap[$companyKey]['logoUrl'] === '' && (string) ($sponsor->getLogoUrl() ?? '') !== '') {
                $companyCardsMap[$companyKey]['logoUrl'] = (string) $sponsor->getLogoUrl();
            }

            if ((int) ($sponsor->getId() ?? 0) > $companyCardsMap[$companyKey]['representativeSponsorId']) {
                $companyCardsMap[$companyKey]['representativeSponsorId'] = (int) $sponsor->getId();
                if ((string) ($sponsor->getContactEmail() ?? '') !== '') {
                    $companyCardsMap[$companyKey]['contactEmail'] = (string) $sponsor->getContactEmail();
                }
            }
        }

        $companyCards = array_values(array_map(static function (array $row): array {
            $row['eventsCount'] = count((array) $row['eventIds']);
            unset($row['eventIds']);

            return $row;
        }, $companyCardsMap));

        usort($companyCards, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'name-asc' => strcasecmp((string) $a['companyName'], (string) $b['companyName']),
                'name-desc' => strcasecmp((string) $b['companyName'], (string) $a['companyName']),
                'contribution-asc' => ((float) $a['contributionTotal']) <=> ((float) $b['contributionTotal']),
                'contribution-desc' => ((float) $b['contributionTotal']) <=> ((float) $a['contributionTotal']),
                'date-asc' => ((int) $a['representativeSponsorId']) <=> ((int) $b['representativeSponsorId']),
                'date-desc' => ((int) $b['representativeSponsorId']) <=> ((int) $a['representativeSponsorId']),
                default => ((float) $b['contributionTotal']) <=> ((float) $a['contributionTotal']),
            };
        });

        $latestByContact = [];
        $latestContributionId = 0;

        // 5) Construire un indicateur d'inactivite par contact a partir de la derniere contribution.
        foreach ($sponsors as $item) {
            $contactKey = mb_strtolower(trim((string) ($item->getContactEmail() ?: $item->getCompanyName() ?: ('sponsor_' . $item->getId()))));
            $contributionId = (int) ($item->getId() ?? 0);
            $latestContributionId = max($latestContributionId, $contributionId);

            if (!isset($latestByContact[$contactKey])) {
                $latestByContact[$contactKey] = [
                    'companyName' => (string) ($item->getCompanyName() ?: '-'),
                    'contactEmail' => (string) ($item->getContactEmail() ?: '-'),
                    'representativeSponsorId' => (int) $item->getId(),
                    'lastContributionId' => $contributionId,
                    'lastContributionAmount' => (float) $item->getContributionAmount(),
                    'contributionTotal' => 0.0,
                    'sponsorshipCount' => 0,
                ];
            }

            $latestByContact[$contactKey]['contributionTotal'] += (float) $item->getContributionAmount();
            $latestByContact[$contactKey]['sponsorshipCount']++;

            if ($contributionId > $latestByContact[$contactKey]['lastContributionId']) {
                $latestByContact[$contactKey] = [
                    'companyName' => (string) ($item->getCompanyName() ?: '-'),
                    'contactEmail' => (string) ($item->getContactEmail() ?: '-'),
                    'representativeSponsorId' => (int) $item->getId(),
                    'lastContributionId' => $contributionId,
                    'lastContributionAmount' => (float) $item->getContributionAmount(),
                    'contributionTotal' => (float) $latestByContact[$contactKey]['contributionTotal'],
                    'sponsorshipCount' => (int) $latestByContact[$contactKey]['sponsorshipCount'],
                ];
            }
        }

        // 6) Detecter les sponsors "froids" en comparant leur derniere activite au rythme global.
        $inactiveSponsorAlerts = array_values(array_filter($latestByContact, static function (array $row) use ($latestContributionId): bool {
            $contributionsSinceLast = max(0, $latestContributionId - $row['lastContributionId']);

            return $contributionsSinceLast >= 3;
        }));

        $inactiveSponsorAlerts = array_map(static function (array $row) use ($latestContributionId): array {
            $contributionsSinceLast = max(0, $latestContributionId - $row['lastContributionId']);
            $contributionTotal = (float) $row['contributionTotal'];
            $sponsorshipCount = (int) $row['sponsorshipCount'];

            // 7) Produire un score de risque metier pour prioriser les relances.
            $riskScore = (int) round(
                min(
                    100,
                    ($contributionsSinceLast * 11.0)
                    + ($contributionTotal < 1500 ? 18 : 0)
                    + ($sponsorshipCount <= 1 ? 12 : 0)
                )
            );

            $severity = 'medium';
            $severityLabel = 'Modere';
            $recommendedAction = 'Suivi automatique';

            if ($riskScore >= 80 || $contributionsSinceLast >= 8) {
                $severity = 'critical';
                $severityLabel = 'Critique';
                $recommendedAction = 'Relance immediate + appel';
            } elseif ($riskScore >= 55 || $contributionsSinceLast >= 5) {
                $severity = 'high';
                $severityLabel = 'Eleve';
                $recommendedAction = 'Relance email prioritaire';
            }

            $row['contributionsSinceLast'] = $contributionsSinceLast;
            $row['riskScore'] = $riskScore;
            $row['severity'] = $severity;
            $row['severityLabel'] = $severityLabel;
            $row['recommendedAction'] = $recommendedAction;

            return $row;
        }, $inactiveSponsorAlerts);

        usort($inactiveSponsorAlerts, static function (array $a, array $b): int {
            $scoreOrder = ((int) $b['riskScore']) <=> ((int) $a['riskScore']);
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }

            return ((int) $b['contributionsSinceLast']) <=> ((int) $a['contributionsSinceLast']);
        });
        $inactiveSponsorAlerts = array_slice($inactiveSponsorAlerts, 0, 8);

        // 8) Charger les statistiques principales qui alimentent les KPI et graphiques.
        $stats = [
            'total' => $this->sponsorRepository->getTotalSponsors(),
            'totalContribution' => $this->sponsorRepository->getTotalContribution(),
            'averageContribution' => $this->sponsorRepository->getAverageContribution(),
            'byEvent' => $this->sponsorRepository->getContributionsByEvent(),
            'topCompanies' => $this->sponsorRepository->getTopCompaniesByContribution(),
        ];

        // 9) Definir une palette commune pour garder une identite visuelle coherente.
        $palette = ['#7c3aed', '#2563eb', '#059669', '#d97706', '#e11d48', '#0891b2', '#ec4899', '#6366f1'];

        // 10) Graphe barre: comparaison des montants de contribution par evenement.
        $topEventContributionMap = array_slice($stats['byEvent'], 0, 5, true);
        $eventLabels = array_map(static fn (mixed $value): string => (string) $value, array_keys($topEventContributionMap));
        $eventValues = array_map(static fn (mixed $value): float => (float) $value, array_values($topEventContributionMap));

        $eventsByContributionChart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $eventsByContributionChart->setData([
            'labels' => $eventLabels,
            'datasets' => [[
                'label' => 'Contributions par evenement (DT)',
                'data' => $eventValues,
                'backgroundColor' => array_slice($palette, 0, max(1, count($eventValues))),
                'borderRadius' => 10,
                'borderWidth' => 0,
            ]],
        ]);
        $eventsByContributionChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => true,
                    'text' => 'Evenements les plus sponsorises',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ]);

        // 11) Graphe horizontal: volume de sponsors par evenement.
        $eventSponsorCountMap = [];
        foreach ($sponsors as $item) {
            $title = (string) ($eventTitleMap[$item->getEventId()] ?? ('Evenement #' . (int) $item->getEventId()));
            $eventSponsorCountMap[$title] = (int) ($eventSponsorCountMap[$title] ?? 0) + 1;
        }
        arsort($eventSponsorCountMap);

        $eventSponsorCountMap = array_slice($eventSponsorCountMap, 0, 5, true);

        $eventVolumeLabels = array_map(static fn (mixed $value): string => (string) $value, array_keys($eventSponsorCountMap));
        $eventVolumeValues = array_map(static fn (mixed $value): int => (int) $value, array_values($eventSponsorCountMap));

        $eventsShareChart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $eventsShareChart->setData([
            'labels' => $eventVolumeLabels,
            'datasets' => [[
                'label' => 'Nombre de sponsors par evenement',
                'data' => $eventVolumeValues,
                'backgroundColor' => array_slice($palette, 0, max(1, count($eventVolumeValues))),
                'borderRadius' => 9,
                'borderWidth' => 0,
            ]],
        ]);
        $eventsShareChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => true,
                    'text' => 'Top 5 volume sponsors par evenement',
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                        'precision' => 0,
                    ],
                ],
            ],
        ]);

        // 12) Graphe donut: repartition des top sponsors par contribution totale.
        $topCompanyLabels = array_map(static fn (mixed $value): string => (string) $value, array_keys($stats['topCompanies']));
        $topCompanyValues = array_map(static fn (mixed $value): float => (float) $value, array_values($stats['topCompanies']));

        $topSponsorsChart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $topSponsorsChart->setData([
            'labels' => $topCompanyLabels,
            'datasets' => [[
                'label' => 'Top sponsors (DT)',
                'data' => $topCompanyValues,
                'backgroundColor' => array_slice($palette, 0, max(1, count($topCompanyValues))),
                'borderColor' => '#ffffff',
                'borderWidth' => 3,
            ]],
        ]);
        $topSponsorsChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => 'Meilleurs sponsors par contribution',
                ],
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '62%',
        ]);

        // 13) Graphe horizontal: nombre de partenariats par sponsor.
        $companyPartnershipCountMap = [];
        foreach ($sponsors as $item) {
            $companyName = (string) ($item->getCompanyName() ?: '-');
            $companyPartnershipCountMap[$companyName] = (int) ($companyPartnershipCountMap[$companyName] ?? 0) + 1;
        }

        $topCompanyPartnershipValues = [];
        foreach ($topCompanyLabels as $companyLabel) {
            $topCompanyPartnershipValues[] = (int) ($companyPartnershipCountMap[$companyLabel] ?? 0);
        }

        $topSponsorsComparisonChart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $topSponsorsComparisonChart->setData([
            'labels' => $topCompanyLabels,
            'datasets' => [[
                'label' => 'Nombre de partenariats',
                'data' => $topCompanyPartnershipValues,
                'backgroundColor' => array_slice($palette, 0, max(1, count($topCompanyValues))),
                'borderRadius' => 9,
                'borderWidth' => 0,
            ]],
        ]);
        $topSponsorsComparisonChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => true,
                    'text' => 'Frequence partenariats Top sponsors',
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
            ],
        ]);

        // 14) Graphe radar: vue globale du portefeuille sponsor.
        $overviewChart = $this->chartBuilder->createChart(Chart::TYPE_RADAR);
        $overviewChart->setData([
            'labels' => ['Sponsors', 'Contribution totale (kDT)', 'Moyenne (kDT)', 'Evenements couverts'],
            'datasets' => [[
                'label' => 'Vue globale sponsor',
                'data' => [
                    (float) $stats['total'],
                    round(((float) $stats['totalContribution']) / 1000, 2),
                    round(((float) $stats['averageContribution']) / 1000, 2),
                    (float) count($stats['byEvent']),
                ],
                'fill' => true,
                'backgroundColor' => 'rgba(124,58,237,0.18)',
                'borderColor' => '#7c3aed',
                'pointBackgroundColor' => '#7c3aed',
                'pointBorderColor' => '#ffffff',
            ]],
        ]);
        $overviewChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'title' => [
                    'display' => true,
                    'text' => 'Vue globale dashboard sponsor',
                ],
            ],
            'scales' => [
                'r' => [
                    'beginAtZero' => true,
                ],
            ],
        ]);

        // 6.b) Graphe droite (vue globale): repartition des alertes par niveau de risque.
        $alertDistributionMap = [
            'Modere' => 0,
            'Eleve' => 0,
            'Critique' => 0,
        ];
        foreach ($inactiveSponsorAlerts as $alertRow) {
            $severityLabel = (string) $alertRow['severityLabel'];
            if (!array_key_exists($severityLabel, $alertDistributionMap)) {
                $alertDistributionMap[$severityLabel] = 0;
            }
            $alertDistributionMap[$severityLabel]++;
        }

        $overviewKpiChart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $overviewKpiChart->setData([
            'labels' => array_keys($alertDistributionMap),
            'datasets' => [[
                'label' => 'Alertes par niveau',
                'data' => array_values($alertDistributionMap),
                'backgroundColor' => ['#f59e0b', '#ea580c', '#dc2626'],
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
            ]],
        ]);
        $overviewKpiChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Distribution des alertes risque',
                ],
            ],
            'cutout' => '56%',
        ]);

        // 7) Rendu final: toutes les donnees de liste + dashboard sont envoyees au template.
        return $this->render('sponsor/admin.html.twig', [
            'pageInfo' => [
                'title' => 'Liste des entreprises',
                'subtitle' => 'Consultez les entreprises partenaires, comparez leurs contributions et suivez les alertes de sponsoring.',
            ],
            'sponsors' => $sponsors,
            'events' => $events,
            'eventTitleMap' => $eventTitleMap,
            'companies' => $this->sponsorRepository->getDistinctCompanies(),
            'search' => $search,
            'selectedCompany' => $company,
            'selectedEventId' => $eventId > 0 ? $eventId : null,
            'sort' => $sort,
            'stats' => $stats,
            'eventsByContributionChart' => $eventsByContributionChart,
            'eventsShareChart' => $eventsShareChart,
            'topSponsorsChart' => $topSponsorsChart,
            'topSponsorsComparisonChart' => $topSponsorsComparisonChart,
            'overviewChart' => $overviewChart,
            'overviewKpiChart' => $overviewKpiChart,
            'inactiveSponsorAlerts' => $inactiveSponsorAlerts,
            'companyCards' => $companyCards,
        ]);
    }

    #[Route('/admin/sponsors/company', name: 'app_sponsors_company_contributions', methods: ['GET'])]
    public function adminCompanyContributions(Request $request): Response
    {
        $this->denyUnlessAdmin();

        $company = trim((string) $request->query->get('company', ''));
        if ($company === '') {
            $this->addFlash('error', 'Entreprise introuvable.');
            return $this->redirectToRoute('app_sponsors_index');
        }

        $sponsors = $this->sponsorRepository->searchForAdmin(null, $company, null);
        if ($sponsors === []) {
            $this->addFlash('error', 'Aucune contribution trouvee pour cette entreprise.');
            return $this->redirectToRoute('app_sponsors_index');
        }

        usort($sponsors, static fn (Sponsor $a, Sponsor $b): int => ((int) ($b->getId() ?? 0)) <=> ((int) ($a->getId() ?? 0)));

        $eventTitleMap = $this->sponsorService->buildEventTitleMapForSponsors($sponsors);

        $contributionTotal = array_reduce(
            $sponsors,
            static fn (float $sum, Sponsor $item): float => $sum + (float) $item->getContributionAmount(),
            0.0
        );

        $eventsCount = count(array_unique(array_filter(
            array_map(static fn (Sponsor $item): int => (int) $item->getEventId(), $sponsors),
            static fn (int $eventId): bool => $eventId > 0
        )));

        return $this->render('sponsor/company_contributions.html.twig', [
            'pageInfo' => [
                'title' => 'Contributions entreprise',
                'subtitle' => 'Visualisez toutes les contributions de cette entreprise et les evenements qu elle soutient.',
            ],
            'companyName' => $company,
            'sponsors' => $sponsors,
            'eventTitleMap' => $eventTitleMap,
            'contributionTotal' => $contributionTotal,
            'eventsCount' => $eventsCount,
        ]);
    }

    #[Route('/admin/sponsors/new', name: 'app_sponsors_new', methods: ['GET', 'POST'])]
    public function adminNew(Request $request): Response
    {
        $this->denyUnlessAdmin();

        $sponsor = new Sponsor();
        $form = $this->createForm(SponsorType::class, $sponsor, [
            'event_choices' => $this->sponsorService->buildEventChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Server-side validation: check contribution field and display red error
            $contributionValue = trim((string) $sponsor->getContributionName());
            if ($contributionValue === '' || $contributionValue === '0' || $contributionValue === '0.00') {
                $form->get('contributionName')->addError(new \Symfony\Component\Form\FormError('La contribution est obligatoire.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($this->sponsorService->hasDuplicateSponsor($sponsor)) {
                    $this->addFlash('error', 'Une entreprise avec le meme email existe deja pour cet evenement.');
                } else {
                $this->sponsorService->hydrateSponsorUserRelation($sponsor);
                $this->applySponsorContributionCurrencyConversion($sponsor, $form);
                $this->handleSponsorUploads($request, $form, $sponsor);
                $this->entityManager->persist($sponsor);
                $this->entityManager->flush();

                $emailSent = $this->sendContributionContractEmail($sponsor);

                $this->addFlash('success', 'Sponsor ajoute avec succes.');
                if (!$emailSent) {
                    $this->addFlash('error', 'Contribution enregistree, mais email contrat non envoye.');
                }
                return $this->redirectToRoute('app_sponsors_index');
                }
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('sponsor/form.html.twig', [
            'pageInfo' => ['title' => 'Ajouter Entreprise Partenaire', 'subtitle' => 'Enregistrez une entreprise sponsor avec ses informations, son evenement et sa contribution.'],
            'form' => $form->createView(),
            'isEdit' => false,
            'backRoute' => 'app_sponsors_index',
        ]);
    }

    #[Route('/admin/sponsors/{id}/edit', name: 'app_sponsors_edit', methods: ['GET', 'POST'])]
    public function adminEdit(Request $request, Sponsor $sponsor): Response
    {
        $this->denyUnlessAdmin();

        $form = $this->createForm(SponsorType::class, $sponsor, [
            'event_choices' => $this->sponsorService->buildEventChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Server-side validation: check contribution field and display red error
            $contributionValue = trim((string) $sponsor->getContributionName());
            if ($contributionValue === '' || $contributionValue === '0' || $contributionValue === '0.00') {
                $form->get('contributionName')->addError(new \Symfony\Component\Form\FormError('La contribution est obligatoire.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($this->sponsorService->hasDuplicateSponsor($sponsor, $sponsor->getId())) {
                    $this->addFlash('error', 'Une autre entreprise avec le meme email existe deja pour cet evenement.');
                } else {
                $this->sponsorService->hydrateSponsorUserRelation($sponsor);
                $this->applySponsorContributionCurrencyConversion($sponsor, $form);
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
            'pageInfo' => ['title' => 'Ajouter Entreprise Partenaire', 'subtitle' => 'Mettez a jour les informations du sponsor pour garder des donnees propres et coherentes.'],
            'form' => $form->createView(),
            'isEdit' => true,
            'sponsor' => $sponsor,
            'backRoute' => 'app_sponsors_index',
        ]);
    }

    #[Route('/admin/sponsors/{id}/details', name: 'app_sponsors_details', methods: ['GET'])]
    public function adminDetails(Sponsor $sponsor): Response
    {
        $this->denyUnlessAdmin();

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
        $this->denyUnlessAdmin();

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
        $this->denyUnlessAdmin();

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
        $this->denyUnlessAdmin();

        if ($this->isCsrfTokenValid('delete_sponsor_' . $sponsor->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($sponsor);
            $this->entityManager->flush();
            $this->addFlash('success', 'Sponsor supprime.');
        }

        return $this->redirectToRoute('app_sponsors_index');
    }

    #[Route('/admin/sponsors/{id}/send-recommendation-email', name: 'app_sponsors_send_recommendation_email', methods: ['POST'])]
    public function adminSendRecommendationEmail(Request $request, Sponsor $sponsor): Response
    {
        $this->denyUnlessAdmin();

        $redirectTo = $request->headers->get('referer') ?: $this->generateUrl('app_sponsors_index');
        $csrfToken = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('send_reco_mail_' . $sponsor->getId(), $csrfToken)) {
            $this->addFlash('error', 'Action refusee: token CSRF invalide.');
            return $this->redirect($redirectTo);
        }

        $email = trim((string) $sponsor->getContactEmail());
        if ($email === '') {
            $this->addFlash('error', 'Aucun email de contact pour cette entreprise.');
            return $this->redirect($redirectTo);
        }

        $result = $this->sponsorAlertEmailService->sendRecommendationEmailForContact($email);
        if ($result['sent'] === true) {
            $this->addFlash('success', 'Email de recommandations envoye a ' . $email . '.');
            return $this->redirect($redirectTo);
        }

        $reason = (string) $result['reason'];
        if ($reason === 'no_recommendations') {
            $this->addFlash('error', 'Aucune recommandation disponible pour ' . $email . '.');
        } elseif ($reason === 'missing_email') {
            $this->addFlash('error', 'Email de contact manquant.');
        } else {
            $transportError = trim((string) ($result['error'] ?? ''));
            $suffix = $transportError !== '' ? (' Details SMTP: ' . $transportError) : '';
            $this->addFlash('error', 'Echec envoi email vers ' . $email . '.' . $suffix);
        }

        return $this->redirect($redirectTo);
    }

    #[Route('/admin/sponsors/export/csv', name: 'app_sponsors_export_csv', methods: ['GET'])]
    public function adminExportCsv(Request $request): Response
    {
        $this->denyUnlessAdmin();

        $mode = strtolower(trim((string) $request->query->get('mode', 'all')));
        if ($mode === 'filtered') {
            $search = trim((string) $request->query->get('q', ''));
            $company = trim((string) $request->query->get('company', ''));
            $eventId = (int) $request->query->get('event_id', 0);

            $items = $this->sponsorRepository->searchForAdmin(
                $search !== '' ? $search : null,
                $company !== '' ? $company : null,
                $eventId > 0 ? $eventId : null
            );
        } else {
            $items = $this->sponsorRepository->findBy([], ['id' => 'DESC']);
        }

        return $this->buildCsvResponse($items, 'sponsors_admin_export.csv');
    }

    #[Route('/admin/sponsors/suggestions', name: 'app_sponsors_suggestions', methods: ['GET'])]
    public function adminSuggestions(Request $request): Response
    {
        $this->denyUnlessAdmin();

        $q = trim((string) $request->query->get('q', ''));
        if (strlen($q) < 1) {
            return $this->json([]);
        }

        $sponsors = $this->sponsorRepository->findAll();
        $searchLower = mb_strtolower($q);
        $suggestions = [];
        $seen = [];

        foreach ($sponsors as $sponsor) {
            $companyName = $sponsor->getCompanyName() ?? '';
            if (!empty($companyName) && mb_stripos($companyName, $searchLower) !== false && !in_array($companyName, $seen)) {
                $suggestions[] = $companyName;
                $seen[] = $companyName;
            }
            if (count($suggestions) >= 10) break;
        }

        return $this->json($suggestions);
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
        $sponsoredEventIds = array_values(array_unique(array_filter(
            array_map(static fn (Sponsor $sponsor): int => (int) $sponsor->getEventId(), $mySponsors),
            static fn (int $eventId): bool => $eventId > 0
        )));

        $allEvents = $this->sponsorService->fetchEventsCatalog();
        $recommendedEvents = $isSponsorSession
            ? $this->sponsorService->buildRecommendedEvents($allEvents, $user, $email)
            : array_slice($allEvents, 0, 6);

        if ($isSponsorSession && $sponsoredEventIds !== []) {
            $recommendedEvents = array_values(array_filter(
                $recommendedEvents,
                static fn (array $event): bool => !in_array((int) $event['id'], $sponsoredEventIds, true)
            ));
        }

        if ($query !== '') {
            $needle = mb_strtolower($query);
            $mySponsors = array_values(array_filter($mySponsors, function (Sponsor $sponsor) use ($needle): bool {
                $eventTitle = (string) $this->sponsorService->getEventTitleById((int) $sponsor->getEventId());
                return str_contains(mb_strtolower($eventTitle), $needle);
            }));

            $allEvents = array_values(array_filter($allEvents, static function (array $event) use ($needle): bool {
                return str_contains(mb_strtolower((string) $event['title']), $needle);
            }));

            $recommendedEvents = array_values(array_filter($recommendedEvents, static function (array $event) use ($needle): bool {
                return str_contains(mb_strtolower((string) $event['title']), $needle);
            }));
        }

        $allEvents = $this->sponsorService->sortEvents($allEvents, $sort);
        $recommendedEvents = $this->sponsorService->sortEvents($recommendedEvents, $sort);
        $mySponsors = $this->sponsorService->sortSponsors($mySponsors, $mySort);

        $eventBuckets = $this->sponsorService->splitEventsByStatus($allEvents);
        $sponsorableEvents = $this->sponsorService->sortEvents($eventBuckets['sponsorable'], $sort);
        if ($isSponsorSession && $sponsoredEventIds !== []) {
            $sponsorableEvents = array_values(array_filter(
                $sponsorableEvents,
                static fn (array $event): bool => !in_array((int) $event['id'], $sponsoredEventIds, true)
            ));
        }
        $archivedEvents = $this->sponsorService->sortEvents($eventBuckets['archived'], $sort);
        $recommendedEvents = $this->sponsorService->sortEvents(array_values(array_filter(
            $recommendedEvents,
            fn (array $event): bool => $this->sponsorService->resolveEventStatus($event['startDate'] ?? null, $event['endDate'] ?? null)['key'] !== 'termine'
        )), $sort);

        if ($isSponsorSession && $request->hasSession()) {
            $today = (new \DateTimeImmutable())->format('Y-m-d');
            $sessionKey = 'sponsor_reco_mail_last_sent_' . md5(mb_strtolower(trim($email)));
            $lastSentAt = (string) $request->getSession()->get($sessionKey, '');

            if ($lastSentAt !== $today) {
                $sent = $this->sponsorAlertEmailService->sendRecommendationEmailForSponsor($user, $email, $recommendedEvents, $mySponsors);
                if ($sent) {
                    $request->getSession()->set($sessionKey, $today);
                }
            }
        }

        $historyItems = $this->sponsorService->buildSponsorHistory($mySponsors);

        $eventTypeStats = $this->sponsorService->buildEventTypeStats($allEvents);
        $eventMonthStats = $this->sponsorService->buildEventMonthStats($allEvents, 6);
        $contributionsByEvent = $isSponsorSession
            ? $this->sponsorRepository->getMyContributionsByEvent($email)
            : [];

        $upcomingActionAlerts = [];

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
            'upcomingActionAlerts' => $upcomingActionAlerts,
        ]);
    }

    #[Route('/sponsor/portal/suggestions', name: 'app_sponsor_portal_suggestions', methods: ['GET'])]
    public function portalSuggestions(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 1) {
            return $this->json([]);
        }

        $allEvents = $this->sponsorService->fetchEventsCatalog();
        $needle = mb_strtolower($q);
        $suggestions = [];
        $seen = [];

        foreach ($allEvents as $event) {
            $title = (string) $event['title'];
            $titleLower = mb_strtolower($title);
            if ($title !== '' && str_contains($titleLower, $needle) && !in_array($title, $seen, true)) {
                $suggestions[] = [
                    'title' => $title,
                    'location' => (string) $event['location'],
                ];
                $seen[] = $title;
            }
            if (count($suggestions) >= 8) {
                break;
            }
        }

        return $this->json($suggestions);
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
                    return str_contains(mb_strtolower((string) $item['eventTitle']), $needle);
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
                'ongoing' => count(array_filter($historyItems, static fn (array $item): bool => $item['statusKey'] === 'en_cours')),
                'ended' => count(array_filter($historyItems, static fn (array $item): bool => $item['statusKey'] === 'termine')),
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

        $sponsor = new Sponsor();
        $sponsor->setEventId($id);
        
        // Pre-fill company name from user name
        if ($user instanceof UserModel) {
            $fullName = trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? ''));
            if ($fullName !== '') {
                $sponsor->setCompanyName($fullName);
            }
            
            // Pre-fill logo from profile picture
            $profilePic = trim((string) ($user->getProfilePictureUrl() ?? ''));
            if ($profilePic !== '') {
                $sponsor->setLogoUrl($profilePic);
            }
            
            $sponsor->setContactEmail((string) $user->getEmail());
            $sponsor->setUser($user);
        }
        
        if ($fixedEmail !== null && $fixedEmail !== '') {
            $sponsor->setContactEmail($fixedEmail);
        }

        $form = $this->createForm(SponsorType::class, $sponsor, [
            'fixed_email' => $fixedEmail,
            'fixed_event_id' => $id,
            'event_choices' => [$event['title'] => $id],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Server-side validation: check required fields and display red errors
            $hasErrors = false;
            
            // Validate contribution
            $contributionValue = trim((string) $sponsor->getContributionName());
            if ($contributionValue === '' || $contributionValue === '0' || $contributionValue === '0.00') {
                $form->get('contributionName')->addError(new \Symfony\Component\Form\FormError('La contribution est obligatoire.'));
                $hasErrors = true;
            }
            
            // Validate company name
            if ($sponsor->getCompanyName() === null || trim((string) $sponsor->getCompanyName()) === '') {
                $form->get('companyName')->addError(new \Symfony\Component\Form\FormError('Le nom de l\'entreprise est obligatoire.'));
                $hasErrors = true;
            }
            
            // Validate email
            if ($sponsor->getContactEmail() === null || trim((string) $sponsor->getContactEmail()) === '') {
                $form->get('contactEmail')->addError(new \Symfony\Component\Form\FormError('L\'email de contact est obligatoire.'));
                $hasErrors = true;
            }
        }

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
                    $this->applySponsorContributionCurrencyConversion($sponsor, $form);
                    $this->handleSponsorUploads($request, $form, $sponsor);
                    $this->entityManager->persist($sponsor);
                    $this->entityManager->flush();

                    $emailSent = $this->sendContributionContractEmail($sponsor);
                    $this->addFlash('success', 'Sponsoring enregistre avec succes.');
                    if (!$emailSent) {
                        $this->addFlash('error', 'Sponsoring enregistre, mais email contrat non envoye.');
                    }

                    return $this->redirectToRoute('app_sponsor_portal');
                } catch (\RuntimeException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        return $this->render('sponsor/portal_form.html.twig', [
            'pageInfo' => ['title' => 'Sponsoriser un evenement', 'subtitle' => (string) $event['title']],
            'event' => $event,
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

        if ($form->isSubmitted()) {
            // Server-side validation: check required fields and display red errors
            // Validate contribution
            $contributionValue = trim((string) $sponsor->getContributionName());
            if ($contributionValue === '' || $contributionValue === '0' || $contributionValue === '0.00') {
                $form->get('contributionName')->addError(new \Symfony\Component\Form\FormError('La contribution est obligatoire.'));
            }
            
            // Validate company name
            if ($sponsor->getCompanyName() === null || trim((string) $sponsor->getCompanyName()) === '') {
                $form->get('companyName')->addError(new \Symfony\Component\Form\FormError('Le nom de l\'entreprise est obligatoire.'));
            }
            
            // Validate email
            if ($sponsor->getContactEmail() === null || trim((string) $sponsor->getContactEmail()) === '') {
                $form->get('contactEmail')->addError(new \Symfony\Component\Form\FormError('L\'email de contact est obligatoire.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $sponsor->setContactEmail((string) $user->getEmail());
            $sponsor->setUser($user);
            try {
                $this->applySponsorContributionCurrencyConversion($sponsor, $form);
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
            'event' => $event,
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
        // Upload optionnel du logo sponsor.
        $logoFile = $form->has('logoFile') ? $form->get('logoFile')->getData() : null;
        if ($logoFile instanceof UploadedFile) {
            $sponsor->setLogoUrl($this->storeUploadedFile($request, $logoFile, 'logos'));
        }

        // Upload optionnel du contrat source fourni par le sponsor.
        $contractFile = $form->has('contractFile') ? $form->get('contractFile')->getData() : null;
        if ($contractFile instanceof UploadedFile) {
            $sponsor->setContractUrl($this->storeUploadedFile($request, $contractFile, 'contracts'));
        }
    }

    private function applySponsorContributionCurrencyConversion(Sponsor $sponsor, FormInterface $form): void
    {
        // La conversion n'est appliquee que si le formulaire expose une devise differente du TND.
        if (!$form->has('contributionCurrency')) {
            return;
        }

        $currency = strtoupper(trim((string) $form->get('contributionCurrency')->getData()));
        if ($currency === '' || $currency === 'TND') {
            return;
        }

        $amount = (float) $sponsor->getContributionName();
        $converted = $this->currencyConverter->convert($amount, $currency, 'TND');
        $sponsor->setContributionName($converted);
    }

    private function storeUploadedFile(Request $request, UploadedFile $file, string $bucket): string
    {
        // Les documents sponsor sont stockes dans public/uploads pour etre accessibles depuis l'interface.
        $projectDir = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            throw new \RuntimeException('kernel.project_dir must be a string');
        }
        $targetDir = $projectDir . '/public/uploads/sponsor/' . $bucket;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Impossible de creer le dossier d\'upload.');
        }

        $base = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBase = (string) $this->slugger->slug($base !== '' ? $base : 'fichier');
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = $safeBase . '-' . bin2hex(random_bytes(4)) . '.' . (string) $extension;

        try {
            $file->move($targetDir, $filename);
        } catch (FileException $e) {
            throw new \RuntimeException('Echec de l\'upload du fichier.', 0, $e);
        }

        $publicPath = '/uploads/sponsor/' . $bucket . '/' . $filename;
        return rtrim($request->getSchemeAndHttpHost(), '/') . $publicPath;
    }

    /**
     * @param array<string, int|string|null> $backRouteParams
     */
    private function createSponsorContractResponse(Sponsor $sponsor, bool $inline, string $backRoute, array $backRouteParams = []): Response
    {
        // Le contrat est toujours regenere a la demande pour refleter les donnees les plus recentes.
        $pdf = $this->generateContractPdfBytes($sponsor);
        if ($pdf === null) {
            $this->addFlash('error', 'Generation du PDF impossible.');
            return $this->redirectToRoute($backRoute, $backRouteParams);
        }

        $filename = 'contrat-sponsor-' . $this->slugifyFilename((string) ($sponsor->getCompanyName() ?? 'eventflow')) . '.pdf';

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            sprintf('%s; filename="%s"', $inline ? 'inline' : 'attachment', $filename)
        );

        return $response;
    }

    private function generateContractPdfBytes(Sponsor $sponsor): ?string
    {
        // Le template Twig est rendu en HTML puis converti en PDF via Snappy.
        $event = $this->sponsorService->findEventById((int) $sponsor->getEventId());
        $eventTitle = $event['title'] ?? ($this->sponsorService->getEventTitleById((int) $sponsor->getEventId()) ?? '-');

        $html = $this->renderView('sponsor/contract_pdf.html.twig', [
            'sponsor' => $sponsor,
            'event' => $event,
            'eventTitle' => $eventTitle,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        try {
            return $this->pdfGenerator->getOutputFromHtml($html, [
                'encoding' => 'utf-8',
                'enable-local-file-access' => true,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function sendContributionContractEmail(Sponsor $sponsor): bool
    {
        // Si aucun email n'est saisi, on n'essaie pas de generer un envoi.
        $to = trim((string) $sponsor->getContactEmail());
        if ($to === '') {
            return false;
        }

        $pdf = $this->generateContractPdfBytes($sponsor);
        if ($pdf === null) {
            return false;
        }

        $event = $this->sponsorService->findEventById((int) $sponsor->getEventId());
        $eventTitle = (string) ($event['title'] ?? ($this->sponsorService->getEventTitleById((int) $sponsor->getEventId()) ?? 'Evenement'));
        $company = trim((string) ($sponsor->getCompanyName() ?? 'Entreprise'));
        $filename = 'contrat-sponsor-' . $this->slugifyFilename($company !== '' ? $company : 'eventflow') . '.pdf';
        $from = (string) (getenv('MAILER_FROM') ?: ($_ENV['MAILER_FROM'] ?? 'manaimaryem4@gmail.com'));

        // Le mail contient a la fois une version texte, une version HTML et le contrat en piece jointe.
        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('EventFlow: confirmation de contribution et contrat')
            ->text(
                "Bonjour {$company},\n\n" .
                "Votre contribution pour l'evenement {$eventTitle} a bien ete enregistree.\n" .
                "Le contrat sponsor est joint a ce message en PDF.\n\n" .
                "EventFlow"
            )
            ->html(
                '<p>Bonjour <strong>' . htmlspecialchars($company, ENT_QUOTES) . '</strong>,</p>' .
                '<p>Votre contribution pour l\'evenement <strong>' . htmlspecialchars($eventTitle, ENT_QUOTES) . '</strong> a bien ete enregistree.</p>' .
                '<p>Le contrat sponsor est joint a ce message en PDF.</p>' .
                '<p>EventFlow</p>'
            )
            ->attach($pdf, $filename, 'application/pdf');

        try {
            $this->mailer->send($email);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function slugifyFilename(string $value): string
    {
        $safe = (string) $this->slugger->slug($value);
        return $safe !== '' ? strtolower($safe) : 'document';
    }
    /**
     * @param Sponsor[] $items
     */
    private function buildCsvResponse(array $items, string $filename): Response
    {
        // On reconstruit les titres d'evenement pour exporter un CSV lisible hors de l'application.
        $eventTitleMap = $this->sponsorService->buildEventTitleMapForSponsors($items);

        $response = new StreamedResponse(function () use ($items, $eventTitleMap): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // BOM UTF-8: required for clean accents in Excel.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv(
                $out,
                ['id', 'event_id', 'event_title', 'company_name', 'contact_email', 'contribution_tnd', 'industry', 'phone', 'tax_id'],
                ';',
                '"',
                '\\',
                "\r\n"
            );

            foreach ($items as $sponsor) {
                $eventId = (int) ($sponsor->getEventId() ?? 0);
                fputcsv(
                    $out,
                    [
                        (int) ($sponsor->getId() ?? 0),
                        $eventId,
                        $this->sanitizeCsvText($eventTitleMap[$eventId] ?? '-'),
                        $this->sanitizeCsvText($sponsor->getCompanyName()),
                        $this->sanitizeCsvText($sponsor->getContactEmail()),
                        number_format((float) $sponsor->getContributionAmount(), 2, ',', ''),
                        $this->sanitizeCsvText($sponsor->getIndustry()),
                        $this->asExcelTextLiteral($sponsor->getPhone()),
                        $this->asExcelTextLiteral($sponsor->getTaxId()),
                    ],
                    ';',
                    '"',
                    '\\',
                    "\r\n"
                );
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    private function sanitizeCsvText(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        // Avoid accidental Excel formula execution.
        if (preg_match('/^[=\-+@]/', $text) === 1) {
            return "'" . $text;
        }

        return $text;
    }

    private function asExcelTextLiteral(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        $escaped = str_replace('"', '""', $text);

        // Keep values like phone/tax_id as text (no scientific notation, no lost zeros).
        return '="' . $escaped . '"';
    }

    private function denyUnlessAdmin(): void
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            throw $this->createAccessDeniedException('Acces reserve a l administration.');
        }

        // Vérifier via les rôles Symfony (plus fiable)
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return;
        }

        // Verification de secours via roleId (Administrateur=4)
        $roleId = (int) ($user->getRoleId() ?? 0);
        if ($roleId === 4) {
            return;
        }

        throw $this->createAccessDeniedException('Acces reserve a l administration.');
    }
}
