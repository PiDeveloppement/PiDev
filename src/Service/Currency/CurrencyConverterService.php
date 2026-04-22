<?php

namespace App\Service\Currency;

use Swap\Swap;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CurrencyConverterService
{
    public function __construct(
        private readonly Swap $swap,
        private readonly HttpClientInterface $httpClient
    )
    {
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency = 'TND'): float
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === '' || $to === '') {
            throw new \RuntimeException('Devise invalide pour la conversion.');
        }

        $rate = $this->resolveLatestRate($from, $to);
        return round($amount * $rate, 2);
    }

    public function latestRate(string $fromCurrency, string $toCurrency = 'TND'): float
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === '' || $to === '') {
            throw new \RuntimeException('Devise invalide pour la conversion.');
        }

        return round($this->resolveLatestRate($from, $to), 6);
    }

    /**
     * @return array{labels: string[], rates: float[]}
     */
    public function getLastDaysRates(string $fromCurrency, string $toCurrency = 'TND', int $days = 7): array
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));
        $days = max(2, min($days, 30));

        if ($from === '' || $to === '') {
            throw new \RuntimeException('Devise invalide pour l historique des taux.');
        }

        $labels = [];
        $rates = [];
        $currentRate = $this->latestRate($from, $to);

        $startDate = new \DateTimeImmutable(sprintf('-%d days', $days - 1));
        for ($i = 0; $i < $days; $i++) {
            $day = $startDate->modify(sprintf('+%d days', $i));
            $labels[] = $day->format('d/m');

            $rate = $this->fetchHistoricalRate($day, $from, $to);
            if ($rate === null) {
                $rate = !empty($rates) ? (float) end($rates) : (float) $currentRate;
            }

            $rates[] = round($rate, 6);
        }

        return [
            'labels' => $labels,
            'rates' => $rates,
        ];
    }

    private function fetchHistoricalRate(\DateTimeImmutable $date, string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        try {
            $endpoint = sprintf(
                'https://%s.currency-api.pages.dev/v1/currencies/%s.json',
                $date->format('Y-m-d'),
                strtolower($from)
            );
            $response = $this->httpClient->request('GET', $endpoint, ['timeout' => 10]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $payload = $response->toArray(false);
            $rate = (float) ($payload[strtolower($from)][strtolower($to)] ?? 0.0);
            if ($rate > 0) {
                return $rate;
            }

            return null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resolveLatestRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        try {
            return (float) $this->swap->latest(sprintf('%s/%s', $from, $to))->getValue();
        } catch (\Throwable $exception) {
            // Fallback: calcul croise via EUR lorsque le provider ne supporte pas directement la paire.
            try {
                $fromToEur = $from === 'EUR' ? 1.0 : $this->swap->latest(sprintf('%s/%s', $from, 'EUR'))->getValue();
                $eurToTarget = $to === 'EUR' ? 1.0 : $this->swap->latest(sprintf('%s/%s', 'EUR', $to))->getValue();
                return (float) $fromToEur * (float) $eurToTarget;
            } catch (\Throwable $fallbackException) {
                return $this->fetchRateFromErApi($from, $to, $fallbackException);
            }
        }
    }

    private function fetchRateFromErApi(string $from, string $to, \Throwable $previous): float
    {
        try {
            $response = $this->httpClient->request('GET', sprintf('https://open.er-api.com/v6/latest/%s', $from), [
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Reponse distante invalide.');
            }

            $payload = $response->toArray(false);
            $rate = (float) ($payload['rates'][$to] ?? 0.0);
            if ($rate <= 0) {
                throw new \RuntimeException(sprintf('Taux %s/%s introuvable.', $from, $to));
            }

            return $rate;
        } catch (\Throwable $networkException) {
            try {
                $response = $this->httpClient->request('GET', sprintf('https://www.floatrates.com/daily/%s.json', strtolower($from)), [
                    'timeout' => 10,
                ]);

                if ($response->getStatusCode() === 200) {
                    $payload = $response->toArray(false);
                    $targetCode = strtolower($to);
                    $floatRate = (float) ($payload[$targetCode]['rate'] ?? 0.0);
                    if ($floatRate > 0) {
                        return $floatRate;
                    }
                }
            } catch (\Throwable $floatRateException) {
                // On continue vers le dernier fallback local.
            }

            // Dernier recours: valeurs de secours pour maintenir la saisie operationnelle.
            $emergencyRatesToTnd = [
                'USD' => 2.88,
                'EUR' => 3.13,
                'GBP' => 3.66,
                'CHF' => 3.24,
                'CAD' => 2.08,
            ];

            if ($to === 'TND' && isset($emergencyRatesToTnd[$from])) {
                return (float) $emergencyRatesToTnd[$from];
            }

            if ($from === 'TND' && isset($emergencyRatesToTnd[$to]) && (float) $emergencyRatesToTnd[$to] > 0) {
                return 1 / (float) $emergencyRatesToTnd[$to];
            }

            throw new \RuntimeException(
                sprintf('Conversion indisponible pour %s/%s. Verifiez la connexion ou le provider de taux.', $from, $to),
                0,
                $previous
            );
        }
    }
}
