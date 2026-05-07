<?php

namespace App\Service\Resource;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class VoiceRecognitionService
{
    private ParameterBagInterface $parameterBag;
    private bool $running = false;
    private mixed $listener;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    public function setListener(callable $listener): void
    {
        $this->listener = $listener;
    }

    public function startRecognition(): void
    {
        $this->running = true;
        
        // Chemin vers le modèle Vosk
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        if (is_string($projectDir)) {
            $modelPath = $projectDir . '/src/Entity/resource/fr';
        } else {
            $modelPath = '/src/Entity/resource/fr';
        }
        
        if (!is_dir($modelPath)) {
            throw new \Exception("❌ Modèle Vosk introuvable au chemin : " . $modelPath);
        }

        echo "✅ Modèle Vosk trouvé à : " . $modelPath . "\n";
        
        // Ici tu devras implémenter la logique de reconnaissance vocale
        // Pour l'instant, c'est une structure de base
        $this->processAudioStream($modelPath);
    }

    private function processAudioStream(string $modelPath): void
    {
        // Implémentation avec Vosk PHP
        // Tu devras installer l'extension Vosk pour PHP
        
        while ($this->running) {
            // Logique de capture audio et reconnaissance
            // Pour l'instant, simulation
            if ($this->listener) {
                $result = json_encode(['text' => 'commande détectée']);
                call_user_func($this->listener, $result);
            }
            sleep(1);
        }
    }

    public function stopRecognition(): void
    {
        $this->running = false;
    }
}