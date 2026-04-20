<?php

namespace App\Service\Ai;

class ManagementAssistantService
{
    public function __construct(
        private readonly ManagementContextProvider $contextProvider,
        private readonly OllamaClient $ollamaClient
    ) {
    }

    /**
     * @param array<int,array{role:string,content:string}> $history
     */
    public function ask(string $question, array $history = []): string
    {
        $question = trim($question);
        if ($question === '') {
            return 'Pose une question pour commencer.';
        }

        $context = $this->contextProvider->getContext();

        $messages = [
            [
                'role' => 'system',
                'content' => 'Tu es un copilote de gestion pour EventFlow. Reponds en francais, de maniere concrete et actionnable. '
                    . 'Tu aides sur Sponsor, Budget et Depense. Si une information manque, dis-le clairement. '
                    . 'N invente jamais des chiffres absents du contexte.',
            ],
            [
                'role' => 'system',
                'content' => 'Contexte de gestion JSON (temps reel): ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];

        foreach ($this->sanitizeHistory($history) as $item) {
            $messages[] = $item;
        }

        $messages[] = [
            'role' => 'user',
            'content' => $question,
        ];

        return $this->ollamaClient->chat($messages);
    }

    public function getModelName(): string
    {
        return $this->ollamaClient->getModel();
    }

    /**
     * @param array<int,mixed> $history
     * @return array<int,array{role:string,content:string}>
     */
    private function sanitizeHistory(array $history): array
    {
        $normalized = [];

        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $role = isset($entry['role']) ? (string) $entry['role'] : '';
            $content = isset($entry['content']) ? trim((string) $entry['content']) : '';

            if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $normalized[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return array_slice($normalized, -12);
    }
}
