<?php

namespace App\Service\Resource;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class UnsplashService
{
    private HttpClientInterface $client;
    // On définit ta clé ici (comme ton CLIENT_ID en Java)
    private const CLIENT_ID = 'dcWhGYXCIQpKznqdhSTn5ymYZi10wbm4ygCzmfUH33c';

    public function __construct(HttpClientInterface $client)
    {
        // Symfony injecte automatiquement l'outil pour faire des requêtes HTTP
        $this->client = $client;
    }

    public function getImageUrl(string $query): ?string
    {
        try {
            $response = $this->client->request('GET', 'https://api.unsplash.com/search/photos', [
                'query' => [
                    'page' => 1,
                    'query' => $query,
                    'client_id' => self::CLIENT_ID,
                ],
            ]);

            // Convertit le JSON en tableau PHP (plus besoin de JSONObject)
            $data = $response->toArray();

            if (!empty($data['results'])) {
                // On récupère l'URL "regular" comme dans ton code Java
                return $data['results'][0]['urls']['regular'];
            }
        } catch (\Exception $e) {
            // Log l'erreur si besoin
            return null;
        }

        return null;
    }
}