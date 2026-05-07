<?php

namespace App\Service\Sponsor;

class ExternalAiRecommendationService
{
    public function __construct(
        private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions',
        private string $apiKey = 'api-key-20260415220353',
        private string $model = 'openai/gpt-4o-mini'
    ) {
        $this->apiUrl = trim($this->apiUrl);
        $this->apiKey = trim($this->apiKey);
        $this->model = trim($this->model);
    }

    /**
     * @param array<string,mixed> $sponsorProfile
     * @param array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeInterface,endDate:?\DateTimeInterface}> $events
     * @return array<int,array{id:int,title:string,description:string,location:string,startDate:?\DateTimeInterface,endDate:?\DateTimeInterface}>
     */
    public function recommend(array $sponsorProfile, array $events, int $limit = 6): array
    {
        if ($this->apiKey === '' || $events === []) {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $events = array_slice($events, 0, 25);

        $payloadEvents = array_map(static function (array $event): array {
            return [
                'id' => (int) $event['id'],
                'title' => (string) $event['title'],
                'description' => (string) $event['description'],
                'location' => (string) $event['location'],
                'start_date' => ($event['startDate'] instanceof \DateTimeInterface) ? $event['startDate']->format('Y-m-d H:i:s') : null,
                'end_date' => ($event['endDate'] instanceof \DateTimeInterface) ? $event['endDate']->format('Y-m-d H:i:s') : null,
            ];
        }, $events);

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a sponsorship recommendation engine. Return strict JSON only.',
            ],
            [
                'role' => 'user',
                'content' => $this->buildUserPrompt($sponsorProfile, $payloadEvents, $limit),
            ],
        ];

        try {
            $payload = json_encode([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.2,
            ], JSON_UNESCAPED_UNICODE);

            if ($payload === false) {
                return [];
            }

            $ch = curl_init($this->apiUrl);
            if ($ch === false) {
                return [];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                    'HTTP-Referer: http://localhost',
                    'X-Title: PiDev EventFlow',
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 15,
            ]);

            $raw = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($raw) || $raw === '' || $httpCode < 200 || $httpCode >= 300) {
                return [];
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return [];
            }
            $content = (string) ($data['choices'][0]['message']['content'] ?? '');
            if ($content === '') {
                return [];
            }

            $json = $this->extractJsonObject($content);
            if ($json === null) {
                return [];
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded) || !isset($decoded['recommendations']) || !is_array($decoded['recommendations'])) {
                return [];
            }

            $rankedIds = [];
            foreach ($decoded['recommendations'] as $item) {
                $id = (int) ($item['event_id'] ?? 0);
                if ($id > 0) {
                    $rankedIds[] = $id;
                }
            }

            if ($rankedIds === []) {
                return [];
            }

            $eventMap = [];
            foreach ($events as $event) {
                $eventMap[(int) $event['id']] = $event;
            }

            $result = [];
            foreach ($rankedIds as $id) {
                if (isset($eventMap[$id])) {
                    $result[] = $eventMap[$id];
                    if (count($result) >= $limit) {
                        break;
                    }
                }
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $sponsorProfile
     * @param array<int,array<string,mixed>> $events
     */
    private function buildUserPrompt(array $sponsorProfile, array $events, int $limit): string
    {
        $instructions = [
            'Rank the events for this sponsor profile.',
            'Return strict JSON only.',
            'Output format: {"recommendations":[{"event_id":123,"reason":"..."}]}',
            'Max recommendations: ' . $limit,
            'Do not include markdown.',
        ];

        return implode("\n", $instructions)
            . "\n\nSPONSOR_PROFILE:\n"
            . json_encode($sponsorProfile, JSON_UNESCAPED_UNICODE)
            . "\n\nEVENTS:\n"
            . json_encode($events, JSON_UNESCAPED_UNICODE);
    }

    private function extractJsonObject(string $content): ?string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace === false || $lastBrace === false || $lastBrace <= $firstBrace) {
            return null;
        }

        return substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1) ?: null;
    }
}
