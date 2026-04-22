<?php

namespace App\Service\AI;

class ManagementAssistantService
{
    public function __construct(
        private readonly ManagementContextProvider $contextProvider,
        private readonly OllamaClient $ollamaClient
    ) {
    }

    public function generateResponse(string $message, int $userId, array $context = []): string
    {
        $userContext = $this->contextProvider->getContextForUser($userId);
        $mergedContext = array_merge($userContext, $context);

        return $this->ollamaClient->generateResponse($message, $mergedContext);
    }

    public function updateContext(int $userId, array $context): void
    {
        $this->contextProvider->updateContext($userId, $context);
    }
}
