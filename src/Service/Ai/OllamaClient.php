<?php

namespace App\Service\Ai;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeoutSeconds
    ) {
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     */
    public function chat(array $messages): string
    {
        $preferredModel = $this->model;

        $result = $this->requestChat($preferredModel, $messages);
        if ($result['ok']) {
            return $result['content'];
        }

        $error = $result['error'];
        if (stripos($error, 'not found') !== false) {
            $installed = $this->getInstalledModels();
            if ($installed !== []) {
                $fallbackModel = $installed[0];
                $fallbackResult = $this->requestChat($fallbackModel, $messages);
                if ($fallbackResult['ok']) {
                    return $fallbackResult['content'];
                }
            }

            throw new \RuntimeException(
                "Aucun modele utilisable. Le modele configure '{$preferredModel}' est introuvable. "
                . "Installe-le avec: ollama pull {$preferredModel}"
            );
        }

        throw new \RuntimeException('Erreur Ollama: ' . $error);
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{ok:bool,content:string,error:string}
     */
    private function requestChat(string $model, array $messages): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/chat', [
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => false,
                ],
                'timeout' => $this->timeoutSeconds,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Impossible de contacter Ollama. Verifiez que le service est lance.', 0, $e);
        }

        $status = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($status >= 400) {
            $errorMessage = isset($payload['error']) ? (string) $payload['error'] : 'Erreur Ollama inconnue.';
            return [
                'ok' => false,
                'content' => '',
                'error' => $errorMessage,
            ];
        }

        $content = (string) ($payload['message']['content'] ?? '');
        if ($content === '') {
            return [
                'ok' => false,
                'content' => '',
                'error' => 'Reponse Ollama vide.',
            ];
        }

        return [
            'ok' => true,
            'content' => trim($content),
            'error' => '',
        ];
    }

    /**
     * @return string[]
     */
    private function getInstalledModels(): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/api/tags', [
                'timeout' => 10,
            ]);
            $payload = $response->toArray(false);
        } catch (\Throwable) {
            return [];
        }

        $models = [];
        if (!isset($payload['models']) || !is_array($payload['models'])) {
            return $models;
        }

        foreach ($payload['models'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($name !== '') {
                $models[] = $name;
            }
        }

        return array_values(array_unique($models));
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
