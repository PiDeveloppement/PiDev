<?php

namespace App\Controller\Assistant;

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
        // La page d'assistant est reservee a l'administration et aux organisateurs.
        $this->denyUnlessAdminOrOrganizer();

        return $this->render('assistant/index.html.twig', [
            'modelName' => $this->assistantService->getModelName(),
        ]);
    }

    #[Route('/admin/assistant-ia/ask', name: 'app_ai_assistant_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        // Endpoint JSON principal: il recoit une question et renvoie une reponse structuree.
        $this->denyUnlessAdminOrOrganizer();

        $data = $this->parseJsonRequest($request);
        if ($data === null) {
            return $this->jsonError('Corps JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $question = trim((string) ($data['question'] ?? ''));
        $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : [];

        if ($question === '') {
            return $this->jsonError('La question est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        // On transmet la question et l'historique de conversation au service metier IA.
        try {
            $answer = $this->assistantService->ask($question, $history);

            return $this->json([
                'answer' => $answer,
                'model' => $this->assistantService->getModelName(),
            ], Response::HTTP_OK, ['Content-Type' => 'application/json; charset=UTF-8']);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/admin/assistant-ia/send', name: 'app_ai_assistant_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        // Endpoint alternatif compatible avec une interface front qui envoie "message" au lieu de "question".
        $this->denyUnlessAdminOrOrganizer();

        $data = $this->parseJsonRequest($request);
        if ($data === null) {
            return $this->jsonError('Corps JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $question = trim((string) ($data['message'] ?? $data['question'] ?? ''));
        $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : [];

        if ($question === '') {
            return $this->jsonError('Le message est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        // La reponse renvoie a la fois "response" et "answer" pour simplifier l'integration front.
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

    /**
     * @return array<string,mixed>|null
     */
    private function parseJsonRequest(Request $request): ?array
    {
        // Normaliser le body JSON en tableau PHP, sinon retourner null pour signaler une requete invalide.
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => $message,
        ], $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    private function denyUnlessAdminOrOrganizer(): void
    {
        // Controle d'acces double: d'abord via les roles Symfony, puis via le roleId historique.
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
