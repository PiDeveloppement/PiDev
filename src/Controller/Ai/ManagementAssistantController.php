<?php

namespace App\Controller\Ai;

use App\Entity\User\UserModel;
use App\Service\Ai\ManagementAssistantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ManagementAssistantController extends AbstractController
{
    public function __construct(private readonly ManagementAssistantService $assistantService)
    {
    }

    #[Route('/admin/assistant-ia', name: 'app_ai_assistant_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyUnlessAdminOrOrganizer();
        return $this->render('assistant/index.html.twig', [
            'modelName' => $this->assistantService->getModelName(),
        ]);
    }

    #[Route('/admin/assistant-ia/ask', name: 'app_ai_assistant_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $this->denyUnlessAdminOrOrganizer();

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST, ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        $question = trim((string) ($data['question'] ?? ''));
        $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : [];

        if ($question === '') {
            return $this->json(['error' => 'La question est obligatoire.'], Response::HTTP_BAD_REQUEST, ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        try {
            $answer = $this->assistantService->ask($question, $history);
            return $this->json([
                'answer' => $answer,
                'model' => $this->assistantService->getModelName(),
            ], Response::HTTP_OK, ['Content-Type' => 'application/json; charset=UTF-8']);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY, ['Content-Type' => 'application/json; charset=UTF-8']);
        }
    }

    #[Route('/api/ai-chat/send', name: 'app_ai_chat_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $this->denyUnlessAdminOrOrganizer();

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST, ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        $question = trim((string) ($data['message'] ?? $data['question'] ?? ''));
        $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : [];

        if ($question === '') {
            return $this->json(['error' => 'Le message est obligatoire.'], Response::HTTP_BAD_REQUEST, ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        try {
            $answer = $this->assistantService->ask($question, $history);
            return $this->json([
                'response' => $answer,
                'answer' => $answer,
                'model' => $this->assistantService->getModelName(),
                'isSimulated' => false,
            ], Response::HTTP_OK, ['Content-Type' => 'application/json; charset=UTF-8']);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'isSimulated' => false,
            ], Response::HTTP_BAD_GATEWAY, ['Content-Type' => 'application/json; charset=UTF-8']);
        }
    }

    private function denyUnlessAdminOrOrganizer(): void
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            throw $this->createAccessDeniedException('Accès réservé à l’administration et aux organisateurs.');
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_ORGANISATEUR', $roles, true)) {
            return;
        }

        $roleId = (int) ($user->getRoleId() ?? 0);
        if ($roleId === 2 || $roleId === 4) {
            return;
        }

        throw $this->createAccessDeniedException('Accès réservé à l’administration et aux organisateurs.');
    }
}