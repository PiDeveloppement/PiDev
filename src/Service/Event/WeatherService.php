<?php

namespace App\Service\Event;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class WeatherService
{
    private const GOUVERNORATS = [
        'Tunis' => [36.8065, 10.1815],
        'Ariana' => [36.8625, 10.1956],
        'Ben Arous' => [36.7435, 10.2335],
        'Manouba' => [36.8089, 10.0964],
        'Nabeul' => [36.4561, 10.7376],
        'Zaghouan' => [36.4018, 10.1422],
        'Bizerte' => [37.2744, 9.8739],
        'Béja' => [36.7256, 9.1817],
        'Jendouba' => [36.5011, 8.7800],
        'Kef' => [36.1826, 8.7148],
        'Siliana' => [36.0844, 9.3708],
        'Sousse' => [35.8256, 10.6411],
        'Monastir' => [35.7780, 10.8262],
        'Mahdia' => [35.5047, 11.0622],
        'Sfax' => [34.7406, 10.7603],
        'Kairouan' => [35.6781, 10.0963],
        'Kasserine' => [35.1672, 8.8365],
        'Sidi Bouzid' => [35.0382, 9.4858],
        'Gabès' => [33.8815, 10.0982],
        'Medenine' => [33.3549, 10.5055],
        'Tataouine' => [32.9297, 10.4518],
        'Gafsa' => [34.4250, 8.7842],
        'Tozeur' => [33.9197, 8.1335],
        'Kebili' => [33.7049, 8.9690],
    ];

    private const GOVERNORATE_ALIASES = [
        'le kef' => 'Kef',
        'kef' => 'Kef',
        'beja' => 'Béja',
        'béja' => 'Béja',
        'medenine' => 'Medenine',
        'médenine' => 'Medenine',
        'gabes' => 'Gabès',
        'gabès' => 'Gabès',
        'kebili' => 'Kebili',
    ];

    private const WEATHER_CODES = [
        0 => ['description' => 'Ciel dégagé', 'icon' => '☀️'],
        1 => ['description' => 'Partiellement nuageux', 'icon' => '⛅'],
        2 => ['description' => 'Partiellement nuageux', 'icon' => '⛅'],
        3 => ['description' => 'Partiellement nuageux', 'icon' => '⛅'],
        45 => ['description' => 'Brouillard', 'icon' => '🌫️'],
        48 => ['description' => 'Brouillard', 'icon' => '🌫️'],
        51 => ['description' => 'Bruine', 'icon' => '🌦️'],
        53 => ['description' => 'Bruine', 'icon' => '🌦️'],
        55 => ['description' => 'Bruine', 'icon' => '🌦️'],
        61 => ['description' => 'Pluie', 'icon' => '🌧️'],
        63 => ['description' => 'Pluie', 'icon' => '🌧️'],
        65 => ['description' => 'Pluie', 'icon' => '🌧️'],
        71 => ['description' => 'Neige', 'icon' => '❄️'],
        73 => ['description' => 'Neige', 'icon' => '❄️'],
        75 => ['description' => 'Neige', 'icon' => '❄️'],
        80 => ['description' => 'Averses', 'icon' => '🌦️'],
        81 => ['description' => 'Averses', 'icon' => '🌦️'],
        82 => ['description' => 'Averses violentes', 'icon' => '🌧️'],
        95 => ['description' => 'Orage', 'icon' => '⛈️'],
        96 => ['description' => 'Orage', 'icon' => '⛈️'],
        99 => ['description' => 'Orage', 'icon' => '⛈️'],
    ];

    public function __construct()
    {
    }

    public function getCurrentWeatherForGovernorate(string $governorate): array
    {
        $coordinates = $this->resolveCoordinates($governorate);
        if ($coordinates === null) {
            throw new \RuntimeException(sprintf('Gouvernorat inconnu: %s', $governorate));
        }

        [$latitude, $longitude] = $coordinates;
        $httpClient = HttpClient::create();

        try {
            $response = $httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,weather_code,wind_speed_10m,relative_humidity_2m',
                    'timezone' => 'auto',
                    'wind_speed_unit' => 'kmh',
                ],
                'timeout' => 30,
                'max_duration' => 60,
                'verify_peer' => false,
                'verify_host' => false,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'EventFlow/1.0',
                ],
            ]);

            $payload = $response->getContent(false);
            $data = json_decode($payload, true);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException('Impossible de contacter Open-Meteo: ' . $exception->getMessage());
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Réponse météo invalide.');
        }

        if (!is_array($data)) {
            throw new \RuntimeException('Réponse météo invalide.');
        }

        $current = $data['current'] ?? null;
        if (!is_array($current)) {
            throw new \RuntimeException('Données météo indisponibles.');
        }

        $weatherCode = (int) ($current['weather_code'] ?? -1);
        $weatherInfo = self::WEATHER_CODES[$weatherCode] ?? [
            'description' => 'Conditions météorologiques inconnues',
            'icon' => '🌡️',
        ];

        return [
            'governorate' => $this->resolveGovernorateLabel($governorate) ?? $governorate,
            'temperature' => (float) ($current['temperature_2m'] ?? 0),
            'description' => $weatherInfo['description'],
            'icon' => $weatherInfo['icon'],
            'windSpeed' => (float) ($current['wind_speed_10m'] ?? 0),
            'humidity' => (int) ($current['relative_humidity_2m'] ?? 0),
        ];
    }

    private function resolveCoordinates(string $governorate): ?array
    {
        $label = $this->resolveGovernorateLabel($governorate);
        if ($label !== null && isset(self::GOUVERNORATS[$label])) {
            return self::GOUVERNORATS[$label];
        }

        return null;
    }

    private function resolveGovernorateLabel(string $governorate): ?string
    {
        $normalized = $this->normalize($governorate);

        foreach (self::GOUVERNORATS as $label => $coordinates) {
            if ($this->normalize($label) === $normalized) {
                return $label;
            }
        }

        return self::GOVERNORATE_ALIASES[$normalized] ?? null;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower($normalized !== false ? $normalized : $value);
    }
}