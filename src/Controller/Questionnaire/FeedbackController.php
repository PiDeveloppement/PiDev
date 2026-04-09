<?php

namespace App\Controller\Questionnaire;

use App\Entity\Questionnaire\Feedback;
use App\Entity\User\UserModel;
use App\Form\Questionnaire\FeedbackType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/questionnaire/feedback')]
final class FeedbackController extends AbstractController
{
    // ------------------------------------------------------------------ INDEX
    #[Route('', name: 'app_questionnaire_feedback_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $allFeedbacks = $entityManager
            ->getRepository(Feedback::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $usersLastFeedback = [];
        $usersInfo         = [];

        foreach ($allFeedbacks as $feedback) {
            $userId = $feedback->getUserId();
            if (isset($usersLastFeedback[$userId])) continue;

            if ($feedback->getComments() !== "Réponse automatique (Quiz)" &&
                $feedback->getEtoiles() > 0) {
                $usersLastFeedback[$userId] = $feedback;
                $user = $entityManager->getRepository(UserModel::class)->find($userId);
                if ($user) {
                    $usersInfo[$userId] = $user->getFullName();
                }
            }
        }

        $feedbacksList  = array_values($usersLastFeedback);
        $totalAvis      = count($feedbacksList);
        $totalEtoiles   = array_sum(array_map(fn($f) => $f->getEtoiles(), $feedbacksList));
        $moyenneEtoiles = $totalAvis > 0 ? round($totalEtoiles / $totalAvis, 1) : 0;

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($feedbacksList as $f) {
            $e = $f->getEtoiles();
            if (isset($distribution[$e])) $distribution[$e]++;
        }

        return $this->render('questionnaire/feedback/show_all.html.twig', [
            'userFeedbacks'  => $feedbacksList,
            'usersInfo'      => $usersInfo,
            'totalAvis'      => $totalAvis,
            'moyenneEtoiles' => $moyenneEtoiles,
            'distribution'   => $distribution,
        ]);
    }

    // ------------------------------------------------------------------ SHOW ALL
    #[Route('/show-all', name: 'app_questionnaire_feedback_show_all', methods: ['GET'])]
    public function showAllFeedbacks(EntityManagerInterface $entityManager): Response
    {
        return $this->redirectToRoute('app_questionnaire_feedback_index');
    }

    // ------------------------------------------------------------------ STATS
    #[Route('/stats', name: 'app_questionnaire_feedback_stats', methods: ['GET'])]
    public function stats(EntityManagerInterface $entityManager): Response
    {
        $allFeedbacks = $entityManager
            ->getRepository(Feedback::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $usersLastFeedback = [];
        $usersInfo         = [];

        foreach ($allFeedbacks as $feedback) {
            $userId = $feedback->getUserId();
            if (isset($usersLastFeedback[$userId])) continue;

            if ($feedback->getComments() !== "Réponse automatique (Quiz)" &&
                $feedback->getEtoiles() > 0) {
                $usersLastFeedback[$userId] = $feedback;
                $user = $entityManager->getRepository(UserModel::class)->find($userId);
                if ($user) {
                    $usersInfo[$userId] = $user->getFullName();
                }
            }
        }

        $feedbacksList    = array_values($usersLastFeedback);
        $totalAvis        = count($feedbacksList);
        $totalEtoiles     = array_sum(array_map(fn($f) => $f->getEtoiles(), $feedbacksList));
        $moyenneEtoiles   = $totalAvis > 0 ? round($totalEtoiles / $totalAvis, 1) : 0;
        $distribution     = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($feedbacksList as $f) {
            $e = $f->getEtoiles();
            if (isset($distribution[$e])) $distribution[$e]++;
        }

        // Statistiques avancées
        $satisfaits       = ($distribution[4] + $distribution[5]);
        $tauxSatisfaction = $totalAvis > 0 ? round($satisfaits / $totalAvis * 100) : 0;
        $neutres          = $distribution[3];
        $insatisfaits     = ($distribution[1] + $distribution[2]);
        
        // Évolution mensuelle (derniers 6 mois)
        $monthlyStats = [];
        $now = new \DateTime();
        for ($i = 5; $i >= 0; $i--) {
            $month = clone $now;
            $month->modify("-$i months");
            $monthStart = clone $month;
            $monthStart->modify('first day of this month')->setTime(0, 0, 0);
            $monthEnd = clone $month;
            $monthEnd->modify('last day of this month')->setTime(23, 59, 59);
            
            $monthFeedbacks = array_filter($feedbacksList, function($f) use ($monthStart, $monthEnd) {
                return $f->getCreatedAt() >= $monthStart && $f->getCreatedAt() <= $monthEnd;
            });
            
            $monthlyStats[] = [
                'month' => $month->format('M Y'),
                'count' => count($monthFeedbacks),
                'avgRating' => count($monthFeedbacks) > 0 ? 
                    round(array_sum(array_map(fn($f) => $f->getEtoiles(), $monthFeedbacks)) / count($monthFeedbacks), 1) : 0
            ];
        }

        // Top commentaires
        $topComments = array_slice($feedbacksList, 0, 6);

        // Données mensuelles pour le graphique
        $monthlyData = [];
        $currentDate = new \DateTime();
        
        // Générer les 6 derniers mois
        for ($i = 5; $i >= 0; $i--) {
            $date = clone $currentDate;
            $date->modify("-$i months");
            $monthKey = $date->format('Y-m');
            $monthName = $date->format('M Y');
            
            $count = 0;
            foreach ($feedbacksList as $feedback) {
                if ($feedback->getCreatedAt() && $feedback->getCreatedAt()->format('Y-m') === $monthKey) {
                    $count++;
                }
            }
            
            $monthlyData[] = [
                'month' => $monthName,
                'count' => $count
            ];
        }
        
        // Calculer la tendance
        $trend = 'stable';
        $trendIcon = 'fas fa-minus';
        $trendColor = 'text-secondary';
        
        if (count($monthlyData) >= 2) {
            $lastMonth = $monthlyData[count($monthlyData) - 1]['count'];
            $previousMonth = $monthlyData[count($monthlyData) - 2]['count'];
            
            if ($lastMonth > $previousMonth) {
                $trend = 'en hausse';
                $trendIcon = 'fas fa-arrow-up';
                $trendColor = 'text-success';
            } elseif ($lastMonth < $previousMonth) {
                $trend = 'en baisse';
                $trendIcon = 'fas fa-arrow-down';
                $trendColor = 'text-danger';
            }
        }

        return $this->render('questionnaire/feedback/stats.html.twig', [
            'userFeedbacks'    => $feedbacksList,
            'usersInfo'        => $usersInfo,
            'totalAvis'        => $totalAvis,
            'moyenneEtoiles'   => $moyenneEtoiles,
            'distribution'     => $distribution,
            'satisfaits'       => $satisfaits,
            'tauxSatisfaction' => $tauxSatisfaction,
            'neutres'          => $neutres,
            'insatisfaits'     => $insatisfaits,
            'monthlyStats'     => $monthlyStats,
            'monthlyData'      => $monthlyData,
            'trend'            => $trend,
            'trendIcon'        => $trendIcon,
            'trendColor'       => $trendColor,
            'topComments'      => $topComments,
        ]);
    }

    // ------------------------------------------------------------------ PDF EXPORT
    #[Route('/pdf', name: 'app_questionnaire_feedback_pdf', methods: ['GET'])]
    public function exportPdf(EntityManagerInterface $entityManager): Response
    {
        $allFeedbacks = $entityManager
            ->getRepository(Feedback::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $usersLastFeedback = [];
        $usersInfo         = [];

        foreach ($allFeedbacks as $feedback) {
            $userId = $feedback->getUserId();
            if (isset($usersLastFeedback[$userId])) continue;

            if ($feedback->getComments() !== "Réponse automatique (Quiz)" &&
                $feedback->getEtoiles() > 0) {
                $usersLastFeedback[$userId] = $feedback;
                $user = $entityManager->getRepository(UserModel::class)->find($userId);
                if ($user) {
                    $usersInfo[$userId] = $user->getFullName();
                }
            }
        }

        $feedbacksList    = array_values($usersLastFeedback);
        $totalAvis        = count($feedbacksList);
        $totalEtoiles     = array_sum(array_map(fn($f) => $f->getEtoiles(), $feedbacksList));
        $moyenneEtoiles   = $totalAvis > 0 ? round($totalEtoiles / $totalAvis, 1) : 0;
        $distribution     = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($feedbacksList as $f) {
            $e = $f->getEtoiles();
            if (isset($distribution[$e])) $distribution[$e]++;
        }

        $satisfaits       = ($distribution[4] + $distribution[5]);
        $tauxSatisfaction = $totalAvis > 0 ? round($satisfaits / $totalAvis * 100) : 0;
        $date             = (new \DateTime())->format('d/m/Y H:i');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
          body        { font-family: DejaVu Sans, sans-serif; color: #1a1a2e; font-size: 12px; padding: 28px; }
          h1          { font-size: 22px; margin-bottom: 4px; color: #1a1a2e; }
          .sub        { color: #8a8898; font-size: 11px; margin-bottom: 24px; }
          h2          { font-size: 14px; margin: 24px 0 10px; border-bottom: 2px solid #e8e4dc; padding-bottom: 6px; }
          table.kpi   { width: 100%; border-collapse: separate; border-spacing: 10px; margin-bottom: 20px; }
          .kpi-cell   { padding: 14px 16px; border-radius: 10px; }
          .kpi-label  { font-size: 9px; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 4px; }
          .kpi-value  { font-size: 26px; font-weight: bold; }
          table.data  { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 24px; }
          table.data th { background: #f5f3ee; padding: 7px 10px; text-align: left; font-size: 9px;
                          text-transform: uppercase; letter-spacing: .07em; color: #8a8898;
                          border-bottom: 2px solid #e8e4dc; }
          table.data td { padding: 8px 10px; border-bottom: 1px solid #f0ede6; vertical-align: top; }
          table.data tr:nth-child(even) td { background: #fafaf8; }
          .stars      { color: #d4a010; font-size: 13px; }
          .badge-note { display: inline-block; padding: 2px 9px; border-radius: 20px; font-weight: bold; font-size: 10px; }
          .bar-row    { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
          .bar-label  { min-width: 30px; font-size: 12px; font-weight: bold; color: #d4a010; }
          .bar-track  { flex: 1; height: 9px; background: #e8e4dc; border-radius: 4px; overflow: hidden; }
          .bar-fill   { height: 100%; border-radius: 4px; background: #5b4fcf; }
          .bar-count  { min-width: 28px; font-size: 11px; color: #8a8898; text-align: right; }
          .footer     { margin-top: 32px; font-size: 10px; color: #aaa; text-align: center;
                        border-top: 1px solid #e8e4dc; padding-top: 12px; }
          .comment    { font-style: italic; color: #555; }
        </style></head><body>';

        $html .= '<h1>Rapport — Avis des Utilisateurs</h1>';
        $html .= '<div class="sub">Exporté le ' . $date . ' &nbsp;·&nbsp; ' . $totalAvis . ' avis au total</div>';

        $html .= '<table class="kpi"><tr>';
        $html .= '<td class="kpi-cell" style="background:#edeafc;border:1px solid #bdb5f5;"><div class="kpi-label" style="color:#5b4fcf;">Total avis</div><div class="kpi-value">' . $totalAvis . '</div></td>';
        $html .= '<td class="kpi-cell" style="background:#fef3d0;border:1px solid #e8d080;"><div class="kpi-label" style="color:#a06000;">Note moyenne</div><div class="kpi-value">' . $moyenneEtoiles . ' / 5</div></td>';
        $html .= '<td class="kpi-cell" style="background:#d6f0ee;border:1px solid #a8ddd9;"><div class="kpi-label" style="color:#0d6b65;">Taux satisfaction</div><div class="kpi-value">' . $tauxSatisfaction . ' %</div></td>';
        $html .= '<td class="kpi-cell" style="background:#fde8e0;border:1px solid #f5bfa5;"><div class="kpi-label" style="color:#c0392b;">Avis positifs (4-5&#9733;)</div><div class="kpi-value">' . $satisfaits . '</div></td>';
        $html .= '</tr></table>';

        $html .= '<h2>Distribution des notes</h2>';
        $maxDist = max(array_values($distribution)) ?: 1;
        for ($note = 5; $note >= 1; $note--) {
            $cnt = $distribution[$note] ?? 0;
            $pct = round($cnt / $maxDist * 100);
            $html .= '<div class="bar-row"><div class="bar-label">' . $note . ' &#9733;</div>'
                   . '<div class="bar-track"><div class="bar-fill" style="width:' . $pct . '%;"></div></div>'
                   . '<div class="bar-count">' . $cnt . '</div></div>';
        }

        $html .= '<h2>Liste des avis</h2>';
        $html .= '<table class="data"><thead><tr><th>Utilisateur</th><th>Note</th><th>Commentaire</th><th>Date</th></tr></thead><tbody>';

        $sorted = $feedbacksList;
        usort($sorted, fn($a, $b) => ($b->getEtoiles() ?? 0) - ($a->getEtoiles() ?? 0));

        foreach ($sorted as $f) {
            $userId   = $f->getUserId();
            $userName = $usersInfo[$userId] ?? ('Utilisateur #' . $userId);
            $etoiles  = $f->getEtoiles() ?? 0;
            $dateStr  = $f->getCreatedAt() ? $f->getCreatedAt()->format('d/m/Y') : '—';

            if ($etoiles >= 4)      { $badgeBg = '#d6f0ee'; $badgeColor = '#0d6b65'; }
            elseif ($etoiles === 3) { $badgeBg = '#fef3d0'; $badgeColor = '#a06000'; }
            else                    { $badgeBg = '#fde8e0'; $badgeColor = '#c0392b'; }

            $starsHtml = str_repeat('&#9733;', $etoiles) . str_repeat('&#9734;', 5 - $etoiles);

            $html .= '<tr>'
                   . '<td><strong>' . htmlspecialchars($userName) . '</strong></td>'
                   . '<td><span class="badge-note" style="background:' . $badgeBg . ';color:' . $badgeColor . ';">'
                   .     '<span class="stars">' . $starsHtml . '</span> ' . $etoiles . '/5</span></td>'
                   . '<td class="comment">' . htmlspecialchars($f->getComments() ?? '—') . '</td>'
                   . '<td>' . $dateStr . '</td>'
                   . '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">EventFlow — Rapport des avis — Exporté le ' . $date . '</div>';
        $html .= '</body></html>';

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'avis_utilisateurs_' . date('Ymd_His') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    // ------------------------------------------------------------------ SHOW
    // IMPORTANT : /{id} toujours après les routes statiques
    #[Route('/{id}', name: 'app_questionnaire_feedback_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $feedback = $entityManager->getRepository(Feedback::class)->find($id);
        
        if (!$feedback) {
            throw $this->createNotFoundException('Feedback non trouvé');
        }
        
        return $this->render('questionnaire/feedback/show.html.twig', [
            'feedback' => $feedback,
        ]);
    }

    // ------------------------------------------------------------------ EDIT
    #[Route('/{id}/edit', name: 'app_questionnaire_feedback_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        $feedback = $entityManager->getRepository(Feedback::class)->find($id);
        
        if (!$feedback) {
            throw $this->createNotFoundException('Feedback non trouvé');
        }
        
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_questionnaire_feedback_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('questionnaire/feedback/edit.html.twig', [
            'feedback' => $feedback,
            'form'     => $form,
        ]);
    }

    // ------------------------------------------------------------------ DELETE
    #[Route('/{id}', name: 'app_questionnaire_feedback_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        $feedback = $entityManager->getRepository(Feedback::class)->find($id);
        
        if (!$feedback) {
            throw $this->createNotFoundException('Feedback non trouvé');
        }
        
        if ($this->isCsrfTokenValid('delete' . $feedback->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($feedback);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_questionnaire_feedback_index', [], Response::HTTP_SEE_OTHER);
    }
}