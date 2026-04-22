<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeoutSeconds = 60
    ) {
    }

    public function generateResponse(string $prompt, array $context = []): string
    {
        try {
            $messages = $this->buildMessages($prompt, $context);

            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/chat', [
                'timeout' => $this->timeoutSeconds,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false,
                ],
            ]);

            $result = $response->toArray();

            return $result['message']['content'] ?? 'No response generated';
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Failed to connect to Ollama: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \RuntimeException('Ollama request failed: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/api/tags', [
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception) {
            return false;
        }
    }

    private function buildMessages(string $prompt, array $context): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant for EventFlow, an event management platform.',
            ],
        ];

        if (!empty($context)) {
            $messages[] = [
                'role' => 'system',
                'content' => 'Context: ' . json_encode($context),
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        return $messages;
    }
}
