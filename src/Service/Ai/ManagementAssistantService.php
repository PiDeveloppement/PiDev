<?php

namespace App\Service\Ai;

class ManagementAssistantService
{
    public function __construct(
        private readonly ManagementContextProvider $contextProvider,
        private readonly OllamaClient $ollamaClient
    ) {
    }

    /**
     * @param array<int,array{role:string,content:string}> $history
     */
    public function ask(string $question, array $history = []): string
    {
        $question = trim($question);
        if ($question === '') {
            return 'Pose une question pour commencer.';
        }

        $question = $this->repairCommonMojibake($question);
        $normalizedQuestion = $this->normalizeForMatch($question);
        if ($normalizedQuestion === '') {
            return 'Pose une question pour commencer.';
        }

        if ($this->containsAny($normalizedQuestion, ['salut', 'bonjour', 'hello', 'bonsoir', 'coucou'])) {
            return $this->buildHelpAnswer('aide') ?? 'Pose une question claire sur Sponsor, Budget ou Depense.';
        }

        if (!$this->isInScopeQuestion($normalizedQuestion)) {
            return 'Hors perimetre gestion';
        }

        $context = $this->contextProvider->getContext();

        $deterministic = $this->buildDeterministicAnswer($normalizedQuestion, $context);
        if ($deterministic !== null) {
            return $deterministic;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Tu es un copilote de gestion EventFlow. Tu reponds uniquement sur Sponsor, Budget et Depense. '
                    . 'Interdiction stricte: inventer des chiffres, des IDs ou des evenements. '
                    . 'Interdiction stricte: inventer des periodes (mois, annee, 2023, 2024, etc.) si elles ne sont pas explicitement dans le contexte. '
                    . 'N ecris jamais que les donnees sont privees ou confidentielles: ce contexte est autorise pour la reponse. '
                    . 'Si une information manque, dis-le explicitement. '
                    . 'Style obligatoire: utilise des emojis utiles et fais un retour a la ligne apres chaque phrase. '
                    . 'Format obligatoire en francais:\n'
                    . '1) Constat:\n'
                    . '2) Chiffres exacts:\n'
                    . '3) Actions recommandees',
            ],
            [
                'role' => 'system',
                'content' => 'Contexte de gestion JSON (temps reel): '
                    . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];

        foreach ($this->sanitizeHistory($history) as $item) {
            $messages[] = $item;
        }

        $messages[] = [
            'role' => 'user',
            'content' => $question,
        ];

        $answer = $this->ollamaClient->chat($messages);
        $answer = $this->repairCommonMojibake($answer);
        $answer = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $answer) ?? $answer;

        $answer = $this->enforceNoUnsupportedTimeClaims($answer, $normalizedQuestion, $context);
        $answer = $this->enforceNoRawContextLeak($answer, $normalizedQuestion, $context);
        $answer = $this->enforceNoOutOfScopeRefusal($answer, $normalizedQuestion, $context);

        return $this->normalizeAssistantOutput($answer);
    }

    public function getModelName(): string
    {
        return $this->ollamaClient->getModel();
    }

    /**
     * @param array<int,mixed> $history
     * @return array<int,array{role:string,content:string}>
     */
    private function sanitizeHistory(array $history): array
    {
        $normalized = [];

        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $role = isset($entry['role']) ? (string) $entry['role'] : '';
            $content = isset($entry['content']) ? trim((string) $entry['content']) : '';

            if (($role !== 'user' && $role !== 'assistant') || $content === '') {
                continue;
            }

            $normalized[] = [
                'role' => $role,
                'content' => mb_substr($content, 0, 1200),
            ];
        }

        return array_slice($normalized, -8);
    }

    private function isInScopeQuestion(string $question): bool
    {
        return $this->containsAny($question, [
            'sponsor', 'sponsors',
            'budget', 'budgets', 'rentabilite', 'deficit', 'revenu', 'revenus',
            'depense', 'depenses', 'categorie', 'categories',
            'contribution', 'contributions',
            'event', 'evenement', 'evenements',
            'kpi', 'stat', 'statistique', 'statistiques',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildDeterministicAnswer(string $question, array $context): ?string
    {
        $help = $this->buildHelpAnswer($question);
        if ($help !== null) {
            return $help;
        }

        $global = $this->buildGlobalSummaryAnswer($question, $context);
        if ($global !== null) {
            return $global;
        }

        $sponsorEvents = $this->buildSponsorEventsAnswer($question, $context);
        if ($sponsorEvents !== null) {
            return $sponsorEvents;
        }

        $sponsorTemporal = $this->buildSponsorTemporalLimitationAnswer($question, $context);
        if ($sponsorTemporal !== null) {
            return $sponsorTemporal;
        }

        $specificBudget = $this->buildSpecificBudgetByEventAnswer($question, $context);
        if ($specificBudget !== null) {
            return $specificBudget;
        }

        $highestBudget = $this->buildHighestBudgetEventAnswer($question, $context);
        if ($highestBudget !== null) {
            return $highestBudget;
        }

        $mostRentable = $this->buildMostRentableEventAnswer($question, $context);
        if ($mostRentable !== null) {
            return $mostRentable;
        }

        $losses = $this->buildCriticalBudgetsAnswer($question, $context);
        if ($losses !== null) {
            return $losses;
        }

        $categories = $this->buildTopDepenseCategoriesAnswer($question, $context);
        if ($categories !== null) {
            return $categories;
        }

        $lowestSponsor = $this->buildLowestSponsorContributionAnswer($question, $context);
        if ($lowestSponsor !== null) {
            return $lowestSponsor;
        }

        $sponsor = $this->buildSponsorSummaryAnswer($question, $context);
        if ($sponsor !== null) {
            return $sponsor;
        }

        return null;
    }

    private function buildHelpAnswer(string $question): ?string
    {
        if (!$this->containsAny($question, ['aide', 'help', 'exemples', 'que peux tu'])) {
            return null;
        }

        return implode("\n", [
            'Constat:',
            'Je peux repondre sur Sponsor, Budget et Depense a partir des donnees actuelles.',
            '',
            'Chiffres exacts:',
            'Je m appuie uniquement sur la base et le contexte temps reel.',
            '',
            'Actions recommandees:',
            '- Demande "budgets les plus critiques".',
            '- Demande "top categories de depenses".',
            '- Demande "contribution de [nom entreprise]".',
            '- Demande "evenements sponsorises par [nom entreprise]".',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildGlobalSummaryAnswer(string $question, array $context): ?string
    {
        $isGlobalIntent =
            $this->containsAny($question, ['resume global', 'resum global', 'rsum global', 'vue globale', 'bilan global', 'kpi global'])
            || (
                $this->containsAny($question, ['resume', 'resum', 'rsum', 'bilan', 'vue', 'kpi'])
                && $this->containsAny($question, ['global', 'generale', 'general'])
            );

        if (!$isGlobalIntent) {
            return null;
        }

        /** @var array<string,mixed> $budgets */
        $budgets = isset($context['budgets']) && is_array($context['budgets']) ? $context['budgets'] : [];
        /** @var array<string,mixed> $depenses */
        $depenses = isset($context['depenses']) && is_array($context['depenses']) ? $context['depenses'] : [];
        /** @var array<string,mixed> $sponsors */
        $sponsors = isset($context['sponsors']) && is_array($context['sponsors']) ? $context['sponsors'] : [];

        return implode("\n", [
            'Constat:',
            'Vue globale de gestion Sponsor/Budget/Depense.',
            '',
            'Chiffres exacts:',
            sprintf('- Budgets: %d | Initial: %s DT | Depenses: %s DT | Revenus: %s DT | Rentabilite: %s DT',
                (int) ($budgets['count'] ?? 0),
                $this->formatMoney((float) ($budgets['totalInitial'] ?? 0.0)),
                $this->formatMoney((float) ($budgets['totalExpenses'] ?? 0.0)),
                $this->formatMoney((float) ($budgets['totalRevenue'] ?? 0.0)),
                $this->formatMoney((float) ($budgets['totalRentabilite'] ?? 0.0))
            ),
            sprintf('- Depenses: %d | Montant total: %s DT',
                (int) ($depenses['count'] ?? 0),
                $this->formatMoney((float) ($depenses['totalAmount'] ?? 0.0))
            ),
            sprintf('- Sponsors: %d | Contribution totale: %s DT | Moyenne: %s DT',
                (int) ($sponsors['count'] ?? 0),
                $this->formatMoney((float) ($sponsors['totalContribution'] ?? 0.0)),
                $this->formatMoney((float) ($sponsors['averageContribution'] ?? 0.0))
            ),
            '',
            'Actions recommandees:',
            '- Traiter en priorite les budgets en deficit.',
            '- Verrouiller un plafond sur les categories de depense dominantes.',
            '- Concentrer le suivi relationnel sur les top sponsors.',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function enforceNoRawContextLeak(string $answer, string $normalizedQuestion, array $context): string
    {
        $looksLikeContextLeak =
            stripos($answer, 'Contexte de gestion JSON') !== false
            || preg_match('/\{.*"budgets".*"depenses".*"sponsors".*\}/s', $answer) === 1;

        if (!$looksLikeContextLeak) {
            return $answer;
        }

        $fallback = $this->buildGlobalSummaryAnswer($normalizedQuestion, $context);
        if ($fallback !== null) {
            return $fallback;
        }

        return implode("\n", [
            'Constat:',
            'La reponse precedente contenait des donnees brutes difficiles a lire.',
            '',
            'Chiffres exacts:',
            '- Les indicateurs existent mais doivent etre presentes en format synthese.',
            '',
            'Actions recommandees:',
            '- Repose la question en demandant un resume global clair.',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildCriticalBudgetsAnswer(string $question, array $context): ?string
    {
        $isCriticalQuestion = $this->containsAny($question, [
            'budget critique', 'budgets critiques', 'budget en deficit', 'budgets en deficit',
            'budget en perte', 'budgets en perte', 'rentabilite negative',
        ]);

        if (!$isCriticalQuestion) {
            return null;
        }

        /** @var array<int,array<string,mixed>> $critical */
        $critical = isset($context['criticalBudgets']) && is_array($context['criticalBudgets'])
            ? $context['criticalBudgets']
            : [];

        if ($critical === []) {
            return implode("\n", [
                'Constat:',
                'Aucun budget en deficit detecte actuellement.',
                '',
                'Chiffres exacts:',
                '- Aucun enregistrement avec rentabilite < 0.',
                '',
                'Actions recommandees:',
                '- Continuer le suivi hebdomadaire des depenses et revenus.',
            ]);
        }

        $lines = [
            'Constat:',
            'Des budgets sont en deficit et necessitent une action prioritaire.',
            '',
            'Chiffres exacts:',
        ];

        $rank = 1;
        foreach ($critical as $row) {
            $lines[] = sprintf(
                '- %d) %s | Rentabilite: %s DT | Depenses: %s DT | Revenus: %s DT',
                $rank,
                (string) ($row['eventTitle'] ?? 'Evenement inconnu'),
                $this->formatMoney((float) ($row['rentabilite'] ?? 0.0)),
                $this->formatMoney((float) ($row['totalExpenses'] ?? 0.0)),
                $this->formatMoney((float) ($row['totalRevenue'] ?? 0.0))
            );
            $rank++;
        }

        $lines[] = '';
        $lines[] = 'Actions recommandees:';
        $lines[] = '- Reduire les postes de depense les plus lourds sur ces evenements.';
        $lines[] = '- Verifier les revenus attendus non encaisses.';
        $lines[] = '- Revoir la projection budgetaire a court terme.';

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildTopDepenseCategoriesAnswer(string $question, array $context): ?string
    {
        $isCategoryQuestion = $this->containsAny($question, [
            'categorie de depense', 'categories de depense', 'depenses par categorie',
            'top categories', 'coutent le plus',
        ]);

        if (!$isCategoryQuestion) {
            return null;
        }

        /** @var array<string,mixed> $depenses */
        $depenses = isset($context['depenses']) && is_array($context['depenses']) ? $context['depenses'] : [];
        /** @var array<int,array<string,mixed>> $topCategories */
        $topCategories = isset($depenses['topCategories']) && is_array($depenses['topCategories'])
            ? $depenses['topCategories']
            : [];

        if ($topCategories === []) {
            return implode("\n", [
                'Constat:',
                'Aucune categorie de depense exploitable.',
                '',
                'Chiffres exacts:',
                '- Aucune ligne disponible dans topCategories.',
                '',
                'Actions recommandees:',
                '- Verifier le remplissage de la categorie dans les depenses.',
            ]);
        }

        $lines = [
            'Constat:',
            'Certaines categories concentrent la plus grande part des depenses.',
            '',
            'Chiffres exacts:',
        ];

        $rank = 1;
        foreach ($topCategories as $row) {
            $lines[] = sprintf(
                '- %d) %s: %s DT',
                $rank,
                (string) ($row['category'] ?? 'N/A'),
                $this->formatMoney((float) ($row['total'] ?? 0.0))
            );
            $rank++;
        }

        $lines[] = '';
        $lines[] = 'Actions recommandees:';
        $lines[] = '- Fixer un plafond par categorie sur les 2 premieres categories.';
        $lines[] = '- Suivre un ecart hebdomadaire reel vs prevision.';

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildSpecificBudgetByEventAnswer(string $question, array $context): ?string
    {
        $isBudgetQuestion = $this->containsAny($question, [
            'budget de', 'budget du', 'rentabilite de', 'depenses de', 'revenus de',
            'detail budget', 'details budget',
        ]);
        if (!$isBudgetQuestion) {
            return null;
        }

        $snapshots = isset($context['budgetSnapshots']) && is_array($context['budgetSnapshots'])
            ? $context['budgetSnapshots']
            : [];
        if ($snapshots === []) {
            return null;
        }

        foreach ($snapshots as $row) {
            if (!is_array($row)) {
                continue;
            }

            $eventTitle = (string) ($row['eventTitle'] ?? '');
            $eventTitleNormalized = $this->normalizeForMatch($eventTitle);
            if ($eventTitleNormalized === '' || !str_contains($question, $eventTitleNormalized)) {
                continue;
            }

            return implode("\n", [
                'Constat:',
                sprintf('Budget retrouve pour l evenement "%s".', $eventTitle),
                '',
                'Chiffres exacts:',
                sprintf('- Budget initial: %s DT', $this->formatMoney((float) ($row['initialBudget'] ?? 0.0))),
                sprintf('- Depenses: %s DT', $this->formatMoney((float) ($row['totalExpenses'] ?? 0.0))),
                sprintf('- Revenus: %s DT', $this->formatMoney((float) ($row['totalRevenue'] ?? 0.0))),
                sprintf('- Rentabilite: %s DT', $this->formatMoney((float) ($row['rentabilite'] ?? 0.0))),
                '',
                'Actions recommandees:',
                '- Si rentabilite negative: reduire les depenses non critiques et securiser des revenus supplementaires.',
            ]);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildHighestBudgetEventAnswer(string $question, array $context): ?string
    {
        $isHighestBudgetQuestion =
            $this->containsAny($question, ['budget'])
            && $this->containsAny($question, ['plus haut', 'plus grand', 'max', 'maximum']);

        if (!$isHighestBudgetQuestion) {
            return null;
        }

        /** @var array<int,array<string,mixed>> $snapshots */
        $snapshots = isset($context['budgetSnapshots']) && is_array($context['budgetSnapshots'])
            ? $context['budgetSnapshots']
            : [];
        if ($snapshots === []) {
            return null;
        }

        $best = null;
        $bestValue = null;
        foreach ($snapshots as $row) {
            if (!is_array($row)) {
                continue;
            }
            $initial = (float) ($row['initialBudget'] ?? 0.0);
            if ($bestValue === null || $initial > $bestValue) {
                $bestValue = $initial;
                $best = $row;
            }
        }

        if (!is_array($best) || $bestValue === null) {
            return null;
        }

        return implode("\n", [
            'Constat:',
            sprintf('L evenement avec le budget initial le plus eleve est "%s".', (string) ($best['eventTitle'] ?? 'Evenement inconnu')),
            '',
            'Chiffres exacts:',
            sprintf('- Budget initial: %s DT', $this->formatMoney($bestValue)),
            sprintf('- Depenses: %s DT', $this->formatMoney((float) ($best['totalExpenses'] ?? 0.0))),
            sprintf('- Revenus: %s DT', $this->formatMoney((float) ($best['totalRevenue'] ?? 0.0))),
            '',
            'Actions recommandees:',
            '- Verifier si ce niveau de budget se traduit par une rentabilite suffisante.',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildMostRentableEventAnswer(string $question, array $context): ?string
    {
        $isMostRentableQuestion =
            $this->containsAny($question, ['evenement', 'event'])
            && $this->containsAny($question, ['plus rentable', 'rentable', 'meilleure rentabilite', 'rentabilite max']);

        if (!$isMostRentableQuestion) {
            return null;
        }

        /** @var array<int,array<string,mixed>> $snapshots */
        $snapshots = isset($context['budgetSnapshots']) && is_array($context['budgetSnapshots'])
            ? $context['budgetSnapshots']
            : [];
        if ($snapshots === []) {
            return null;
        }

        $best = null;
        $bestRent = null;
        foreach ($snapshots as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rent = (float) ($row['rentabilite'] ?? 0.0);
            if ($bestRent === null || $rent > $bestRent) {
                $bestRent = $rent;
                $best = $row;
            }
        }

        if (!is_array($best) || $bestRent === null) {
            return null;
        }

        return implode("\n", [
            'Constat:',
            sprintf('L evenement le plus rentable est "%s".', (string) ($best['eventTitle'] ?? 'Evenement inconnu')),
            '',
            'Chiffres exacts:',
            sprintf('- Rentabilite: %s DT', $this->formatMoney($bestRent)),
            sprintf('- Budget initial: %s DT', $this->formatMoney((float) ($best['initialBudget'] ?? 0.0))),
            sprintf('- Depenses: %s DT', $this->formatMoney((float) ($best['totalExpenses'] ?? 0.0))),
            sprintf('- Revenus: %s DT', $this->formatMoney((float) ($best['totalRevenue'] ?? 0.0))),
            '',
            'Actions recommandees:',
            '- Reproduire les facteurs de performance de cet evenement sur les prochains plans budgetaires.',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildSponsorTemporalLimitationAnswer(string $question, array $context): ?string
    {
        $isSponsorCadenceQuestion =
            $this->containsAny($question, ['sponsor', 'contribution'])
            && $this->containsAny($question, ['par mois', 'mensuel', 'par an', 'annuel', 'annee', 'annees', 'mois']);

        if (!$isSponsorCadenceQuestion) {
            return null;
        }

        /** @var array<string,mixed> $sponsors */
        $sponsors = isset($context['sponsors']) && is_array($context['sponsors']) ? $context['sponsors'] : [];
        $companyMap = isset($sponsors['companyContributions']) && is_array($sponsors['companyContributions'])
            ? $sponsors['companyContributions']
            : [];

        $resolvedCompany = $this->resolveCompanyFromQuestion($question, array_keys($companyMap));
        if ($resolvedCompany !== null) {
            $amount = (float) ($companyMap[$resolvedCompany] ?? 0.0);

            return implode("\n", [
                'Constat:',
                sprintf('Je trouve la contribution cumulee du sponsor "%s", mais pas une serie mensuelle/annuelle fiable.', $resolvedCompany),
                '',
                'Chiffres exacts:',
                sprintf('- Contribution totale cumulee connue: %s DT', $this->formatMoney((float) $amount)),
                '- Periode detaillee (mois/annee): non disponible dans le contexte actuel.',
                '',
                'Actions recommandees:',
                '- Ajouter une date de contribution exploitable pour calculer le mensuel et l annuel.',
                '- En attendant, utiliser uniquement la contribution totale cumulee.',
            ]);
        }

        return implode("\n", [
            'Constat:',
            'La question demande une cadence mensuelle/annuelle, mais cette granularite n est pas disponible ici.',
            '',
            'Chiffres exacts:',
            '- Je ne peux pas produire un calcul mensuel ou annuel fiable sans donnees temporelles detaillees.',
            '',
            'Actions recommandees:',
            '- Donner le nom exact du sponsor pour afficher sa contribution totale cumulee.',
            '- Ajouter des donnees par date si tu veux des analyses par mois/annee.',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildSponsorSummaryAnswer(string $question, array $context): ?string
    {
        /** @var array<string,mixed> $sponsors */
        $sponsors = isset($context['sponsors']) && is_array($context['sponsors']) ? $context['sponsors'] : [];

        $specificCompany = $this->buildSpecificSponsorContributionAnswer($question, $sponsors);
        if ($specificCompany !== null) {
            return $specificCompany;
        }

        $isSponsorSummaryQuestion = $this->containsAny($question, [
            'diagnostic sponsor', 'resume sponsor', 'vue globale sponsor', 'top sponsor', 'top entreprise sponsor',
        ]);

        if (!$isSponsorSummaryQuestion) {
            return null;
        }

        $count = (int) ($sponsors['count'] ?? 0);
        $total = (float) ($sponsors['totalContribution'] ?? 0.0);
        $avg = (float) ($sponsors['averageContribution'] ?? 0.0);
        $topCompanies = isset($sponsors['topCompanies']) && is_array($sponsors['topCompanies'])
            ? $sponsors['topCompanies']
            : [];

        $lines = [
            'Constat:',
            'Vue synthese des sponsors actifs.',
            '',
            'Chiffres exacts:',
            sprintf('- Nombre de sponsors: %d', $count),
            sprintf('- Contribution totale: %s DT', $this->formatMoney($total)),
            sprintf('- Contribution moyenne: %s DT', $this->formatMoney($avg)),
        ];

        if ($topCompanies !== []) {
            $lines[] = '- Top entreprises:';
            $rank = 1;
            foreach ($topCompanies as $company => $amount) {
                $lines[] = sprintf(
                    '  %d) %s: %s DT',
                    $rank,
                    (string) $company,
                    $this->formatMoney((float) $amount)
                );
                $rank++;
            }
        }

        $lines[] = '';
        $lines[] = 'Actions recommandees:';
        $lines[] = '- Consolider les sponsors a forte contribution.';
        $lines[] = '- Relancer les sponsors inactifs avec recommandations ciblees.';

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildLowestSponsorContributionAnswer(string $question, array $context): ?string
    {
        $isLowestIntent =
            $this->containsAny($question, ['sponsor'])
            && $this->containsAny($question, ['plus bas', 'plus faible', 'bas'])
            && $this->containsAny($question, ['contribution', 'contributions']);

        if (!$isLowestIntent) {
            return null;
        }

        /** @var array<string,mixed> $sponsors */
        $sponsors = isset($context['sponsors']) && is_array($context['sponsors']) ? $context['sponsors'] : [];
        /** @var array<string,float|int|string> $companyMap */
        $companyMap = isset($sponsors['companyContributions']) && is_array($sponsors['companyContributions'])
            ? $sponsors['companyContributions']
            : [];

        if ($companyMap === []) {
            return implode("\n", [
                'Constat:',
                'Je ne trouve pas de contributions par sponsor dans le contexte.',
                '',
                'Chiffres exacts:',
                '- companyContributions est vide.',
                '',
                'Actions recommandees:',
                '- Verifier que des sponsors avec montants existent en base.',
            ]);
        }

        $lowestCompany = null;
        $lowestAmount = null;
        foreach ($companyMap as $company => $amount) {
            $value = (float) $amount;
            if ($lowestAmount === null || $value < $lowestAmount) {
                $lowestAmount = $value;
                $lowestCompany = (string) $company;
            }
        }

        if ($lowestCompany === null || $lowestAmount === null) {
            return null;
        }

        return implode("\n", [
            'Constat:',
            sprintf('Le sponsor avec la plus faible contribution totale est "%s".', $lowestCompany),
            '',
            'Chiffres exacts:',
            sprintf('- Contribution totale: %s DT', $this->formatMoney($lowestAmount)),
            sprintf('- Sponsors analyses: %d', count($companyMap)),
            '',
            'Actions recommandees:',
            '- Comparer ce sponsor aux 3 premiers contributeurs pour identifier un plan de relance adapte.',
        ]);
    }

    /**
     * @param array<string,mixed> $sponsors
     */
    private function buildSpecificSponsorContributionAnswer(string $question, array $sponsors): ?string
    {
        $companyMap = isset($sponsors['companyContributions']) && is_array($sponsors['companyContributions'])
            ? $sponsors['companyContributions']
            : [];

        if ($companyMap === []) {
            return null;
        }

        $isSpecificSponsorQuestion = $this->containsAny($question, [
            'contribution de', 'contribution du sponsor', 'combien a contribue', 'combien contribue',
        ]);

        if (!$isSpecificSponsorQuestion) {
            return null;
        }

        $resolvedCompany = $this->resolveCompanyFromQuestion($question, array_keys($companyMap));
        if ($resolvedCompany !== null) {
            $amount = (float) ($companyMap[$resolvedCompany] ?? 0.0);
            return implode("\n", [
                'Constat:',
                sprintf('Contribution retrouvee pour le sponsor "%s".', $resolvedCompany),
                '',
                'Chiffres exacts:',
                sprintf('- Contribution totale: %s DT', $this->formatMoney((float) $amount)),
                '',
                'Actions recommandees:',
                '- Verifier si la contribution est en progression sur les derniers evenements.',
            ]);
        }

        return 'Je n ai pas trouve ce sponsor. Donne le nom exact de l entreprise.';
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildSponsorEventsAnswer(string $question, array $context): ?string
    {
        $looksLikeSponsorEventQuestion =
            $this->containsAny($question, ['evenement', 'evenements', 'event', 'events'])
            && $this->containsAny($question, ['sponsorise', 'sponsorises', 'sponsoris', 'partenaire']);

        if (!$looksLikeSponsorEventQuestion) {
            return null;
        }

        /** @var array<string,mixed> $sponsors */
        $sponsors = isset($context['sponsors']) && is_array($context['sponsors']) ? $context['sponsors'] : [];
        $companyEvents = isset($sponsors['companyEvents']) && is_array($sponsors['companyEvents'])
            ? $sponsors['companyEvents']
            : [];

        if ($companyEvents === []) {
            return 'Je n ai pas de donnees de sponsoring par evenement pour le moment.';
        }

        $resolvedCompany = $this->resolveCompanyFromQuestion($question, array_keys($companyEvents));
        if ($resolvedCompany !== null) {
            $events = $companyEvents[$resolvedCompany] ?? [];

            if (!is_array($events) || $events === []) {
                return sprintf('Je n ai trouve aucun evenement sponsorise pour %s.', $resolvedCompany);
            }

            $lines = [
                'Constat:',
                sprintf('Evenements sponsorises par %s.', $resolvedCompany),
                '',
                'Chiffres exacts:',
            ];

            $rank = 1;
            foreach ($events as $eventTitle) {
                $title = trim((string) $eventTitle);
                if ($title === '') {
                    continue;
                }
                $lines[] = sprintf('- %d) %s', $rank, $title);
                $rank++;
            }

            $lines[] = '';
            $lines[] = 'Actions recommandees:';
            $lines[] = '- Comparer ces evenements avec la performance de contribution pour prioriser les prochaines relances.';

            return implode("\n", $lines);
        }

        return 'Je n ai pas reconnu le sponsor dans la question. Donne le nom exact de l entreprise.';
    }

    /**
     * @param array<int|string,mixed> $companyNames
     */
    private function resolveCompanyFromQuestion(string $question, array $companyNames): ?string
    {
        $normalizedQuestion = $this->normalizeForMatch($question);
        if ($normalizedQuestion === '') {
            return null;
        }

        foreach ($companyNames as $name) {
            $company = trim((string) $name);
            $normalizedCompany = $this->normalizeForMatch($company);
            if ($normalizedCompany !== '' && str_contains($normalizedQuestion, $normalizedCompany)) {
                return $company;
            }
        }

        foreach ($companyNames as $name) {
            $company = trim((string) $name);
            $tokens = $this->tokenizeForMatch($company);
            if ($tokens === []) {
                continue;
            }

            $allFound = true;
            foreach ($tokens as $token) {
                if (!str_contains($normalizedQuestion, $token)) {
                    $allFound = false;
                    break;
                }
            }

            if ($allFound) {
                return $company;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function tokenizeForMatch(string $value): array
    {
        $normalized = $this->normalizeForMatch($value);
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $t): bool => mb_strlen($t) >= 3));

        return array_values(array_unique($tokens));
    }

    /**
     * @param string[] $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeForMatch(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        if ($lower === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        $normalized = $ascii !== false ? $ascii : $lower;
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }

    /**
     * Normalise la rÃ©ponse de l'assistant : casse insensible, ajout d'Ã©mojis, gestion UTF-8.
     */
    private function normalizeAssistantOutput(string $text): string
    {
        $text = $this->repairCommonMojibake($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = str_replace(['€', ' EUR', ' eur', ' Euros', ' euros'], [' DT', ' DT', ' DT', ' DT', ' DT'], $text);
        $text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        $text = preg_replace('/\s+(Constat:|Chiffres exacts:|Actions?\s+recommand\w*:)/i', "\n$1", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return implode("\n", [
                'Constat:',
                'Je n ai pas pu produire une reponse exploitable.',
                '',
                'Chiffres exacts:',
                '- Aucune sortie exploitable retournee par le modele.',
                '',
                'Actions recommandees:',
                '- Reposer la question en precisant Sponsor, Budget ou Depense.',
            ]);
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));

        foreach ($lines as $i => $line) {
            if (preg_match('/^constat\s*:/i', $line) === 1) {
                $lines[$i] = preg_replace('/^(constat:\s*)+/i', 'Constat: ', $line) ?? $line;
                continue;
            }
            if (preg_match('/^chiffres?\s+exacts?\s*:/i', $line) === 1) {
                $lines[$i] = preg_replace('/^(chiffres?\s+exacts?\s*:\s*)+/i', 'Chiffres exacts: ', $line) ?? $line;
                continue;
            }
            if (preg_match('/^actions?\s+recommand\w*\s*:/i', $line) === 1) {
                $lines[$i] = preg_replace('/^(actions?\s+recommand\w*\s*:\s*)+/i', 'Actions recommandees: ', $line) ?? $line;
                continue;
            }
            if (preg_match('/^\-\s/', $line) === 1) {
                $lines[$i] = '- ' . substr($line, 2);
            }
        }

        $sections = [
            'constat' => [],
            'chiffres' => [],
            'actions' => [],
        ];
        $current = 'constat';
        $foundStructured = false;

        foreach ($lines as $line) {
            if (preg_match('/^constat\s*:\s*(.*)$/i', $line, $m) === 1) {
                $foundStructured = true;
                $current = 'constat';
                $tail = trim((string) ($m[1] ?? ''));
                if ($tail !== '') {
                    $sections[$current][] = $tail;
                }
                continue;
            }
            if (preg_match('/^chiffres?\s+exacts?\s*:\s*(.*)$/i', $line, $m) === 1) {
                $foundStructured = true;
                $current = 'chiffres';
                $tail = trim((string) ($m[1] ?? ''));
                if ($tail !== '') {
                    $sections[$current][] = $tail;
                }
                continue;
            }
            if (preg_match('/^actions?\s+recommand\w*\s*:\s*(.*)$/i', $line, $m) === 1) {
                $foundStructured = true;
                $current = 'actions';
                $tail = trim((string) ($m[1] ?? ''));
                if ($tail !== '') {
                    $sections[$current][] = $tail;
                }
                continue;
            }
            $sections[$current][] = $line;
        }

        if ($foundStructured) {
            $constat = array_values(array_filter($sections['constat'], static fn (string $v): bool => trim($v) !== ''));
            $chiffres = array_values(array_filter($sections['chiffres'], static fn (string $v): bool => trim($v) !== ''));
            $actions = array_values(array_filter($sections['actions'], static fn (string $v): bool => trim($v) !== ''));

            if ($constat === []) {
                $constat = ['Information disponible mais non formulee clairement.'];
            }
            if ($chiffres === []) {
                $chiffres = ['- Donnees chiffrees non extraites proprement.'];
            }
            if ($actions === []) {
                $actions = ['- Repose la question avec un angle plus precis.'];
            }

            return implode("\n", array_merge(
                ['Constat:'],
                $constat,
                [''],
                ['Chiffres exacts:'],
                $chiffres,
                [''],
                ['Actions recommandees:'],
                $actions
            ));
        }

        return implode("\n", [
            'Constat:',
            $this->toAsciiText($text),
            '',
            'Chiffres exacts:',
            '- Reponse reformatee automatiquement.',
            '',
            'Actions recommandees:',
            '- Reposer la question si tu veux un detail plus structure.',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function enforceNoUnsupportedTimeClaims(string $answer, string $normalizedQuestion, array $context): string
    {
        $isSponsorContributionTopic = $this->containsAny($normalizedQuestion, [
            'contribution', 'contributions', 'mensuel', 'annuel', 'par mois', 'par an', 'annee', 'annees',
        ]);
        if (!$isSponsorContributionTopic) {
            return $answer;
        }

        $questionContainsYear = (bool) preg_match('/\b20\d{2}\b/', $normalizedQuestion);
        $answerMentionsYear = (bool) preg_match('/\b20\d{2}\b/', $answer);
        $answerMentionsCadence = $this->containsAny($this->normalizeForMatch($answer), ['par mois', 'mensuel', 'par an', 'annuel']);

        $hasTemporalSponsorSeries = isset($context['sponsors']['monthly']) || isset($context['sponsors']['yearly']);

        if (!$hasTemporalSponsorSeries && ($answerMentionsCadence || ($answerMentionsYear && !$questionContainsYear))) {
            return implode("\n", [
                'Constat:',
                'Je ne peux pas confirmer une contribution mensuelle/annuelle fiable avec les donnees actuelles.',
                '',
                'Chiffres exacts:',
                '- Les valeurs temporelles (mois/annee) ne sont pas disponibles dans le contexte sponsor charge.',
                '',
                'Actions recommandees:',
                '- Je peux donner la contribution totale cumulee du sponsor si tu me donnes son nom exact.',
                '- Pour du mensuel/annuel exact, il faut une serie par date de contribution.',
            ]);
        }

        return $answer;
    }
    /**
     * @param array<string,mixed> $context
     */
    private function enforceNoOutOfScopeRefusal(string $answer, string $normalizedQuestion, array $context): string
    {
        $normalizedAnswer = $this->normalizeForMatch($answer);
        $looksLikeBadRefusal =
            $this->containsAny($normalizedAnswer, ['je suis desole', 'je ne peux pas', 'hors perimetre', 'informations privees', 'confidenti'])
            && $this->isInScopeQuestion($normalizedQuestion);

        if (!$looksLikeBadRefusal) {
            return $answer;
        }

        $global = $this->buildGlobalSummaryAnswer($normalizedQuestion, $context);
        if ($global !== null) {
            return $global;
        }

        $critical = $this->buildCriticalBudgetsAnswer($normalizedQuestion, $context);
        if ($critical !== null) {
            return $critical;
        }

        $categories = $this->buildTopDepenseCategoriesAnswer($normalizedQuestion, $context);
        if ($categories !== null) {
            return $categories;
        }

        $sponsor = $this->buildSponsorSummaryAnswer($normalizedQuestion, $context);
        if ($sponsor !== null) {
            return $sponsor;
        }

        return implode("\n", [
            'Constat:',
            'Question en perimetre Sponsor/Budget/Depense, mais la reponse precedente etait invalide.',
            '',
            'Chiffres exacts:',
            '- Aucune valeur exploitable extraite de la reponse precedente.',
            '',
            'Actions recommandees:',
            '- Reposer la question avec un angle clair: budgets critiques, top depenses, ou sponsors.',
        ]);
    }

    private function repairCommonMojibake(string $text): string
    {
        $replacements = [
            '�' => 'e',
            'Ã©' => 'e',
            'Ã¨' => 'e',
            'Ãª' => 'e',
            'Ã«' => 'e',
            'Ã ' => 'a',
            'Ã¢' => 'a',
            'Ã®' => 'i',
            'Ã¯' => 'i',
            'Ã´' => 'o',
            'Ã¶' => 'o',
            'Ã¹' => 'u',
            'Ã»' => 'u',
            'Ã¼' => 'u',
            'Ã§' => 'c',
            'â€™' => "'",
            'â€˜' => "'",
            'â€œ' => '"',
            'â€' => '"',
            'â€“' => '-',
            'â€”' => '-',
            'â€¢' => '-',
            'Â' => '',
            'ðŸ' => '',
        ];

        $clean = strtr($text, $replacements);

        return $this->toAsciiText($clean);
    }

    private function toAsciiText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii === false) {
            return $text;
        }

        $ascii = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $ascii) ?? $ascii;

        return trim($ascii);
    }
}
