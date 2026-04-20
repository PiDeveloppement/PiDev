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
            return $this->json(['error' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $question = trim((string) ($data['question'] ?? ''));
        $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : [];

        if ($question === '') {
            return $this->json(['error' => 'La question est obligatoire.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $answer = $this->assistantService->ask($question, $history);

            return $this->json([
                'answer' => $answer,
                'model' => $this->assistantService->getModelName(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    private function denyUnlessAdminOrOrganizer(): void
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            throw $this->createAccessDeniedException('Acces reserve a l administration et aux organisateurs.');
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_ORGANISATEUR', $roles, true)) {
            return;
        }

        $roleId = (int) ($user->getRoleId() ?? 0);
        if ($roleId === 2 || $roleId === 4) {
            return;
        }

        throw $this->createAccessDeniedException('Acces reserve a l administration et aux organisateurs.');
    }
}
