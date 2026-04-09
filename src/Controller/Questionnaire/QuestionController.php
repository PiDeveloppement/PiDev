<?php

namespace App\Controller\Questionnaire;

use App\Entity\Questionnaire\Question;
use App\Form\Questionnaire\QuestionType;
use App\Repository\Questionnaire\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// Pour le PDF
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/questionnaire')]
class QuestionController extends AbstractController
{
    // ------------------------------------------------------------------ INDEX
    #[Route('/', name: 'app_question_index', methods: ['GET'])]
    public function index(QuestionRepository $questionRepository): Response
    {
        return $this->render('questionnaire/question/index.html.twig', [
            'questions' => $questionRepository->findAll(),
        ]);
    }

    // ------------------------------------------------------------------ NEW
    #[Route('/new', name: 'app_question_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $question = new Question();
        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($question);
            $entityManager->flush();
            $this->addFlash('success', 'Question créée avec succès.');
            return $this->redirectToRoute('app_question_index');
        }

        return $this->render('questionnaire/question/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ------------------------------------------------------------------ EDIT
    #[Route('/{id}/edit', name: 'app_question_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Question $question, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Question mise à jour.');
            return $this->redirectToRoute('app_question_index');
        }

        return $this->render('questionnaire/question/edit.html.twig', [
            'question' => $question,
            'form'     => $form->createView(),
        ]);
    }

    // ------------------------------------------------------------------ DELETE
    #[Route('/{id}/delete', name: 'app_question_delete', methods: ['POST'])]
    public function delete(Request $request, Question $question, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $question->getId(), $request->request->get('_token'))) {
            $entityManager->remove($question);
            $entityManager->flush();
            $this->addFlash('success', 'Question supprimée avec succès.');
        }

        return $this->redirectToRoute('app_question_index');
    }

    // ------------------------------------------------------------------ STATS
    #[Route('/stats', name: 'app_question_stats', methods: ['GET'])]
    public function stats(QuestionRepository $questionRepository): Response
    {
        $questions = $questionRepository->findAll();

        $total     = count($questions);
        $sumPoints = 0;
        $maxPoints = 0;
        $withEvent = 0;
        $byEvent   = [];

        foreach ($questions as $q) {
            $pts = $q->getPoints() ?? 0;
            $sumPoints += $pts;
            if ($pts > $maxPoints) $maxPoints = $pts;

            if ($q->getEvent()) {
                $withEvent++;
                $evTitle = $q->getEvent()->getTitle();
                $byEvent[$evTitle] = ($byEvent[$evTitle] ?? 0) + 1;
            }
        }

        arsort($byEvent); // sort by count desc

        $maxByEvent = $byEvent ? max(array_values($byEvent)) : 1;

        // Top 10 questions by points
        $sorted = $questions;
        usort($sorted, fn($a, $b) => ($b->getPoints() ?? 0) - ($a->getPoints() ?? 0));
        $topQuestions = array_slice($sorted, 0, 10);

        $stats = [
            'total'        => $total,
            'avgPoints'    => $total > 0 ? $sumPoints / $total : 0,
            'maxPoints'    => $maxPoints,
            'withEvent'    => $withEvent,
            'byEvent'      => $byEvent,
            'maxByEvent'   => $maxByEvent,
            'topQuestions' => $topQuestions,
        ];

        return $this->render('questionnaire/question/stats.html.twig', [
            'stats' => $stats,
        ]);
    }

    // ------------------------------------------------------------------ PDF EXPORT
    #[Route('/pdf', name: 'app_question_pdf', methods: ['GET'])]
    public function exportPdf(QuestionRepository $questionRepository): Response
    {
        $questions = $questionRepository->findAll();

        $total     = count($questions);
        $sumPoints = 0;
        $maxPoints = 0;
        $withEvent = 0;
        $byEvent   = [];

        foreach ($questions as $q) {
            $pts = $q->getPoints() ?? 0;
            $sumPoints += $pts;
            if ($pts > $maxPoints) $maxPoints = $pts;
            if ($q->getEvent()) {
                $withEvent++;
                $evTitle = $q->getEvent()->getTitle();
                $byEvent[$evTitle] = ($byEvent[$evTitle] ?? 0) + 1;
            }
        }
        arsort($byEvent);

        $sorted = $questions;
        usort($sorted, fn($a, $b) => ($b->getPoints() ?? 0) - ($a->getPoints() ?? 0));
        $topQuestions = array_slice($sorted, 0, 10);

        $avgPoints = $total > 0 ? round($sumPoints / $total, 1) : 0;
        $date      = (new \DateTime())->format('d/m/Y H:i');

        // Build HTML for PDF
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
          body { font-family: DejaVu Sans, sans-serif; color: #1a1a2e; font-size: 12px; padding: 30px; }
          h1   { font-size: 22px; margin-bottom: 4px; }
          .sub { color: #8a8898; font-size: 11px; margin-bottom: 24px; }
          .kpi-row { display: flex; gap: 12px; margin-bottom: 24px; }
          .kpi { flex: 1; background: #f5f3ee; border-radius: 10px; padding: 14px 16px; border: 1px solid #e8e4dc; }
          .kpi-label { font-size: 9px; text-transform: uppercase; letter-spacing: .08em; color: #8a8898; margin-bottom: 4px; }
          .kpi-value { font-size: 24px; font-weight: bold; }
          h2 { font-size: 14px; margin: 20px 0 10px; border-bottom: 2px solid #e8e4dc; padding-bottom: 6px; }
          table { width: 100%; border-collapse: collapse; font-size: 11px; }
          th { background: #f5f3ee; padding: 7px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .07em; color: #8a8898; border-bottom: 2px solid #e8e4dc; }
          td { padding: 7px 10px; border-bottom: 1px solid #f0ede6; }
          tr:nth-child(even) td { background: #fafaf8; }
          .pts { background: #fef3d0; color: #7a5c00; padding: 2px 8px; border-radius: 20px; font-weight: bold; font-size: 10px; }
          .bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
          .bar-label { min-width: 120px; font-size: 11px; }
          .bar-track { flex: 1; height: 8px; background: #e8e4dc; border-radius: 4px; overflow: hidden; }
          .bar-fill { height: 100%; background: #5b4fcf; border-radius: 4px; }
          .bar-count { min-width: 24px; text-align: right; font-size: 11px; color: #8a8898; }
          .footer { margin-top: 32px; font-size: 10px; color: #aaa; text-align: center; border-top: 1px solid #e8e4dc; padding-top: 12px; }
        </style></head><body>';

        $html .= '<h1>📊 Statistiques — Questions Quiz</h1>';
        $html .= '<div class="sub">Exporté le ' . $date . ' &nbsp;·&nbsp; ' . $total . ' question(s) au total</div>';

        // KPIs
        $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;"><tr>';
        $html .= '<td style="width:25%;background:#edeafc;border-radius:10px;padding:14px 16px;border:1px solid #bdb5f5;"><div style="font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#5b4fcf;margin-bottom:4px;">Total</div><div style="font-size:24px;font-weight:bold;">' . $total . '</div></td>';
        $html .= '<td style="width:5%;"></td>';
        $html .= '<td style="width:25%;background:#d6f0ee;border-radius:10px;padding:14px 16px;border:1px solid #a8ddd9;"><div style="font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#0d6b65;margin-bottom:4px;">Pts moyens</div><div style="font-size:24px;font-weight:bold;">' . $avgPoints . '</div></td>';
        $html .= '<td style="width:5%;"></td>';
        $html .= '<td style="width:25%;background:#fef3d0;border-radius:10px;padding:14px 16px;border:1px solid #e8d080;"><div style="font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#a06000;margin-bottom:4px;">Max pts</div><div style="font-size:24px;font-weight:bold;">' . $maxPoints . '</div></td>';
        $html .= '<td style="width:5%;"></td>';
        $html .= '<td style="width:25%;background:#fde8e0;border-radius:10px;padding:14px 16px;border:1px solid #f5bfa5;"><div style="font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#c0392b;margin-bottom:4px;">Avec event</div><div style="font-size:24px;font-weight:bold;">' . $withEvent . '</div></td>';
        $html .= '</tr></table>';

        // Bar chart
        if (!empty($byEvent)) {
            $html .= '<h2>📅 Questions par événement</h2>';
            $maxVal = max(array_values($byEvent));
            foreach ($byEvent as $ev => $cnt) {
                $pct = $maxVal > 0 ? round($cnt / $maxVal * 100) : 0;
                $html .= '<div class="bar-row">';
                $html .= '<div class="bar-label">' . htmlspecialchars($ev) . '</div>';
                $html .= '<div class="bar-track"><div class="bar-fill" style="width:' . $pct . '%;"></div></div>';
                $html .= '<div class="bar-count">' . $cnt . '</div>';
                $html .= '</div>';
            }
        }

        // Top questions table
        $html .= '<h2>🏆 Top questions (par points)</h2>';
        $html .= '<table><thead><tr><th>#</th><th>Question</th><th>Réponse</th><th>Points</th><th>Événement</th></tr></thead><tbody>';
        foreach ($topQuestions as $i => $q) {
            $html .= '<tr>';
            $html .= '<td>' . ($i + 1) . '</td>';
            $html .= '<td>' . htmlspecialchars($q->getTexte() ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($q->getReponse() ?? '-') . '</td>';
            $html .= '<td><span class="pts">★ ' . $q->getPoints() . ' pts</span></td>';
            $html .= '<td>' . ($q->getEvent() ? htmlspecialchars($q->getEvent()->getTitle()) : '—') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // All questions
        $html .= '<h2>📋 Toutes les questions</h2>';
        $html .= '<table><thead><tr><th>Question</th><th>Réponse</th><th>Points</th><th>Options</th><th>Événement</th></tr></thead><tbody>';
        foreach ($questions as $q) {
            $opts = array_filter([$q->getOption1(), $q->getOption2(), $q->getOption3()]);
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($q->getTexte() ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($q->getReponse() ?? '-') . '</td>';
            $html .= '<td><span class="pts">★ ' . $q->getPoints() . '</span></td>';
            $html .= '<td>' . htmlspecialchars(implode(' / ', $opts)) . '</td>';
            $html .= '<td>' . ($q->getEvent() ? htmlspecialchars($q->getEvent()->getTitle()) : '—') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $html .= '<div class="footer">Questions Quiz — Exporté le ' . $date . '</div>';
        $html .= '</body></html>';

        // Generate PDF with Dompdf
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'questions_stats_' . date('Ymd_His') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}
