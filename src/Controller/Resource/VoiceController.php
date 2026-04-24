<?php

namespace App\Controller\Resource;

use App\Service\Resource\VoiceRecognitionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoiceController extends AbstractController
{
    private $voiceService;

    public function __construct(VoiceRecognitionService $voiceService)
    {
        $this->voiceService = $voiceService;
    }

    #[Route('/voice/start', name: 'voice_start')]
    public function startVoiceRecognition(): Response
    {
        try {
            $this->voiceService->setListener(function($result) {
                // Traitement des commandes vocales détectées
                $data = json_decode($result, true);
                echo "Commande détectée : " . ($data['text'] ?? 'Inconnue') . "\n";
            });

            // Démarrer la reconnaissance (en arrière-plan)
            $this->voiceService->startRecognition();

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Reconnaissance vocale démarrée'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/voice/stop', name: 'voice_stop')]
    public function stopVoiceRecognition(): JsonResponse
    {
        $this->voiceService->stopRecognition();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Reconnaissance vocale arrêtée'
        ]);
    }

    #[Route('/voice/test', name: 'voice_test')]
    public function testVoice(): Response
    {
        return $this->render('voice/test.html.twig', [
            'controller_name' => 'VoiceController'
        ]);
    }
}