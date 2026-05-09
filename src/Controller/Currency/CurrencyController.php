<?php

namespace App\Controller\Currency;

use App\Service\Currency\CurrencyConverterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CurrencyController extends AbstractController
{
    public function __construct(private readonly CurrencyConverterService $currencyConverter)
    {
    }

    #[Route('/currency/convert', name: 'app_currency_convert', methods: ['GET'])]
    public function convert(Request $request): JsonResponse
    {
        $amount = (float) $request->query->get('amount', 0);
        $from = strtoupper(trim((string) $request->query->get('from', 'TND')));
        $to = strtoupper(trim((string) $request->query->get('to', 'TND')));

        if ($amount <= 0) {
            return $this->json([
                'ok' => false,
                'message' => 'Montant invalide.',
            ], 400);
        }

        try {
            $converted = $this->currencyConverter->convert($amount, $from, $to);

            return $this->json([
                'ok' => true,
                'amount' => $amount,
                'from' => $from,
                'to' => $to,
                'converted' => $converted,
            ]);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    #[Route('/currency/history', name: 'app_currency_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $from = strtoupper(trim((string) $request->query->get('from', 'TND')));
        $to = strtoupper(trim((string) $request->query->get('to', 'TND')));
        $days = (int) $request->query->get('days', 7);

        try {
            $history = $this->currencyConverter->getLastDaysRates($from, $to, $days);

            return $this->json([
                'ok' => true,
                'from' => $from,
                'to' => $to,
                'labels' => $history['labels'],
                'rates' => $history['rates'],
            ]);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    #[Route('/currency/rate', name: 'app_currency_rate', methods: ['GET'])]
    public function rate(Request $request): JsonResponse
    {
        $from = strtoupper(trim((string) $request->query->get('from', 'TND')));
        $to = strtoupper(trim((string) $request->query->get('to', 'TND')));

        try {
            $rate = $this->currencyConverter->latestRate($from, $to);

            return $this->json([
                'ok' => true,
                'from' => $from,
                'to' => $to,
                'rate' => $rate,
                'source' => 'FlorianvSwapBundle',
                'fetchedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}