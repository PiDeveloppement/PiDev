<?php

namespace App\Service\Event;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiPosterService
{
    private const HF_MODEL_URL = 'https://router.huggingface.co/hf-inference/models/black-forest-labs/FLUX.1-schnell';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%env(default::HUGGINGFACE_API_TOKEN)%')] private readonly ?string $huggingFaceApiToken = null,
        #[Autowire('%env(default::HUGGINGFACE_TOKEN)%')] private readonly ?string $huggingFaceToken = null
    ) {}

    public function generatePoster(string $prompt): array
    {
        $cleanPrompt = trim($prompt);
        if ($cleanPrompt === '') {
            return [
                'ok' => false,
                'message' => 'Le prompt est obligatoire.',
                'status' => JsonResponse::HTTP_BAD_REQUEST,
            ];
        }

        $token = trim((string) ($this->huggingFaceApiToken ?: $this->huggingFaceToken));

        if ($token === '') {
            return [
                'ok' => false,
                'message' => 'Token Hugging Face manquant. Ajoutez HUGGINGFACE_API_TOKEN ou HUGGINGFACE_TOKEN dans .env.local.',
                'status' => JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            ];
        }

        try {
            $response = $this->httpClient->request('POST', self::HF_MODEL_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'image/png',
                ],
                'json' => [
                    'inputs' => $cleanPrompt,
                    'options' => [
                        'wait_for_model' => true,
                    ],
                ],
                'timeout' => 120,
            ]);
        } catch (TransportExceptionInterface $e) {
            return [
                'ok' => false,
                'message' => 'Erreur reseau pendant la generation: ' . $e->getMessage(),
                'status' => JsonResponse::HTTP_BAD_GATEWAY,
            ];
        }

        $statusCode = $response->getStatusCode();
        $contentType = strtolower((string) $response->getHeaders(false)['content-type'][0] ?? '');
        $rawContent = $response->getContent(false);

        if ($statusCode >= 400) {
            $apiMessage = 'Generation indisponible pour le moment.';
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($rawContent, true);
                if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
                    $apiMessage = $decoded['error'];
                }
            }

            return [
                'ok' => false,
                'message' => $apiMessage,
                'status' => JsonResponse::HTTP_BAD_GATEWAY,
            ];
        }

        if (!str_contains($contentType, 'image/')) {
            return [
                'ok' => false,
                'message' => 'La reponse Hugging Face ne contient pas une image.',
                'status' => JsonResponse::HTTP_BAD_GATEWAY,
            ];
        }

        $relativeDir = '/uploads/posters';
        $absoluteDir = $this->projectDir . '/public' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return [
                'ok' => false,
                'message' => 'Impossible de creer le dossier des posters.',
                'status' => JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            ];
        }

        $filename = sprintf('poster_%s_%s.png', date('Ymd_His'), bin2hex(random_bytes(4)));
        $absolutePath = $absoluteDir . '/' . $filename;
        $bytes = @file_put_contents($absolutePath, $rawContent);

        if ($bytes === false) {
            return [
                'ok' => false,
                'message' => 'Impossible de sauvegarder le poster genere.',
                'status' => JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            ];
        }

        return [
            'ok' => true,
            'imageUrl' => $relativeDir . '/' . $filename,
            'status' => JsonResponse::HTTP_OK,
        ];
    }
}