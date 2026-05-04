<?php

namespace App\Controller\Resource;

use App\Service\Resource\FormFillerVoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class FormFillerVoiceController extends AbstractController
{
    private $voiceService;

    public function __construct(FormFillerVoiceService $voiceService)
    {
        $this->voiceService = $voiceService;
    }

    #[Route('/voice/form/set-context/{context}', name: 'voice_form_set_context')]
    public function setFormContext(string $context): JsonResponse
    {
        try {
            $this->voiceService->setFormContext($context);
            
            return new JsonResponse([
                'status' => 'success',
                'message' => "Contexte défini: $context",
                'available_commands' => $this->voiceService->getAvailableCommands()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/voice/form/command', name: 'voice_form_command', methods: ['POST'])]
    public function processVoiceCommand(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['command'])) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Commande manquante'
            ], 400);
        }

        try {
            $result = $this->voiceService->processVoiceCommand($data['command']);
            
            return new JsonResponse([
                'status' => 'success',
                'result' => $result,
                'current_form_data' => $this->voiceService->getFormData(),
                'context' => $this->voiceService->getCurrentContext(),
                'waiting_for_value' => $this->voiceService->isWaitingForValue(),
                'waiting_field' => $this->voiceService->getWaitingField()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/voice/form/data', name: 'voice_form_data')]
    public function getFormData(): JsonResponse
    {
        return new JsonResponse([
            'context' => $this->voiceService->getCurrentContext(),
            'form_data' => $this->voiceService->getFormData(),
            'available_commands' => $this->voiceService->getAvailableCommands()
        ]);
    }

    #[Route('/voice/form/clear', name: 'voice_form_clear')]
    public function clearFormData(): JsonResponse
    {
        $this->voiceService->clearFormData();
        
        return new JsonResponse([
            'status' => 'success',
            'message' => 'Données du formulaire effacées'
        ]);
    }

    #[Route('/voice/form/test', name: 'voice_form_test')]
    public function testFormFiller()
    {
        return $this->render('voice/form_test.html.twig', [
            'contexts' => ['salle', 'equipement', 'reservation']
        ]);
    }
}