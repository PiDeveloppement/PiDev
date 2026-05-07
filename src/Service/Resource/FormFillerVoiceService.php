<?php

namespace App\Service\Resource;

use App\Entity\Resource\Salle;
use App\Entity\Resource\Equipement;
use App\Entity\Resource\ReservationResource;

class FormFillerVoiceService extends VoiceRecognitionService
{
    private ?string $currentContext = null;
    /** @var array<string, mixed> */
    private array $formData = [];
    private ?string $waitingForFieldValue = null;

    // Contextes de formulaire disponibles
    const CONTEXTS = [
        'salle' => Salle::class,
        'equipement' => Equipement::class,
        'reservation' => ReservationResource::class
    ];

    public function setFormContext(string $context): void
    {
        if (isset(self::CONTEXTS[$context])) {
            $this->currentContext = $context;
            $this->formData = [];
            echo "📝 Contexte défini : $context\n";
        } else {
            throw new \InvalidArgumentException("Contexte non reconnu: $context");
        }
    }

    /**
     * Traite une commande vocale et retourne le résultat
     * @return array{action: string, field?: string, value?: mixed, command?: string, error?: string}
     */
    public function processVoiceCommand(string $command): array
    {
        if (!$this->currentContext) {
            return ['action' => 'error', 'error' => 'Aucun contexte de formulaire défini'];
        }

        $command = strtolower(trim($command));

        // Mode direct : si on attend une valeur pour un champ
        if ($this->waitingForFieldValue) {
            $field = $this->waitingForFieldValue;
            $this->formData[$field] = $command;
            $this->waitingForFieldValue = null;
            
            return [
                'action' => 'field_filled', 
                'field' => $field, 
                'value' => $command,
                'message' => "✅ $field : $command"
            ];
        }

        // Mode direct : détecter les noms de champs simples
        $fieldMapping = $this->getFieldMapping();
        
        foreach ($fieldMapping as $keyword => $field) {
            if ($command === $keyword) {
                $this->waitingForFieldValue = $field;
                return [
                    'action' => 'waiting_for_value',
                    'field' => $field,
                    'message' => "🎤 Quelle valeur pour $field ?"
                ];
            }
        }

        // Si c'est une commande complète (ancien mode)
        switch ($this->currentContext) {
            case 'salle':
                return $this->processSalleCommand($command);
            case 'equipement':
                return $this->processEquipementCommand($command);
            case 'reservation':
                return $this->processReservationCommand($command);
        }

        return ['action' => 'not_recognized', 'command' => $command];
    }

    /**
     * Retourne le mapping des champs vocaux vers les propriétés
     * @return array<string, string>
     */
    private function getFieldMapping(): array
    {
        $mappings = [];
        
        if ($this->currentContext === 'salle') {
            $mappings = [
                'nom' => 'name',
                'capacité' => 'capacity',
                'bâtiment' => 'building',
                'étage' => 'floor',
                'statut' => 'status'
            ];
        } elseif ($this->currentContext === 'equipement') {
            $mappings = [
                'nom' => 'name',
                'type' => 'equipement_type',
                'quantité' => 'quantity',
                'statut' => 'status'
            ];
        } elseif ($this->currentContext === 'reservation') {
            $mappings = [
                'type' => 'resourceType',
                'début' => 'startTime',
                'fin' => 'endTime',
                'quantité' => 'quantity'
            ];
        }
        
        return $mappings;
    }

    /**
     * Traite les commandes pour les salles
     * @return array{action: string, field?: string, value?: mixed, command?: string}
     */
    private function processSalleCommand(string $command): array
    {
        // Commandes pour la salle
        if (preg_match('/nom\s+(.+)/', $command, $matches)) {
            $this->formData['name'] = trim($matches[1]);
            return ['action' => 'field_filled', 'field' => 'name', 'value' => $this->formData['name']];
        }

        if (preg_match('/capacité\s+(\d+)/', $command, $matches)) {
            $this->formData['capacity'] = (int)$matches[1];
            return ['action' => 'field_filled', 'field' => 'capacity', 'value' => $this->formData['capacity']];
        }

        if (preg_match('/bâtiment\s+(.+)/', $command, $matches)) {
            $this->formData['building'] = trim($matches[1]);
            return ['action' => 'field_filled', 'field' => 'building', 'value' => $this->formData['building']];
        }

        if (preg_match('/étage\s+(\d+)/', $command, $matches)) {
            $this->formData['floor'] = (int)$matches[1];
            return ['action' => 'field_filled', 'field' => 'floor', 'value' => $this->formData['floor']];
        }

        if (preg_match('/statut\s+(.+)/', $command, $matches)) {
            $status = strtoupper(trim($matches[1]));
            if (in_array($status, ['DISPONIBLE', 'OCCUPEE'])) {
                $this->formData['status'] = $status;
                return ['action' => 'field_filled', 'field' => 'status', 'value' => $this->formData['status']];
            }
        }

        return ['action' => 'not_recognized', 'command' => $command];
    }

    /**
     * Traite les commandes pour les équipements
     * @return array{action: string, field?: string, value?: mixed, command?: string}
     */
    private function processEquipementCommand(string $command): array
    {
        // Commandes pour l'équipement
        if (preg_match('/nom\s+(.+)/', $command, $matches)) {
            $this->formData['name'] = trim($matches[1]);
            return ['action' => 'field_filled', 'field' => 'name', 'value' => $this->formData['name']];
        }

        if (preg_match('/type\s+(.+)/', $command, $matches)) {
            $this->formData['equipement_type'] = trim($matches[1]);
            return ['action' => 'field_filled', 'field' => 'equipement_type', 'value' => $this->formData['equipement_type']];
        }

        if (preg_match('/quantité\s+(\d+)/', $command, $matches)) {
            $this->formData['quantity'] = (int)$matches[1];
            return ['action' => 'field_filled', 'field' => 'quantity', 'value' => $this->formData['quantity']];
        }

        if (preg_match('/statut\s+(.+)/', $command, $matches)) {
            $status = strtoupper(trim($matches[1]));
            if (in_array($status, ['DISPONIBLE', 'INDISPONIBLE', 'MAINTENANCE'])) {
                $this->formData['status'] = $status;
                return ['action' => 'field_filled', 'field' => 'status', 'value' => $this->formData['status']];
            }
        }

        return ['action' => 'not_recognized', 'command' => $command];
    }

    /**
     * Traite les commandes pour les réservations
     * @return array{action: string, field?: string, value?: mixed, command?: string}
     */
    private function processReservationCommand(string $command): array
    {
        // Commandes pour la réservation
        if (preg_match('/type\s+(salle|équipement)/', $command, $matches)) {
            $type = strtoupper($matches[1] === 'salle' ? 'SALLE' : 'EQUIPEMENT');
            $this->formData['resourceType'] = $type;
            return ['action' => 'field_filled', 'field' => 'resourceType', 'value' => $this->formData['resourceType']];
        }

        if (preg_match('/début\s+(.+)/', $command, $matches)) {
            $this->formData['startTime'] = $this->parseDate($matches[1]);
            return ['action' => 'field_filled', 'field' => 'startTime', 'value' => $this->formData['startTime']];
        }

        if (preg_match('/fin\s+(.+)/', $command, $matches)) {
            $this->formData['endTime'] = $this->parseDate($matches[1]);
            return ['action' => 'field_filled', 'field' => 'endTime', 'value' => $this->formData['endTime']];
        }

        if (preg_match('/quantité\s+(\d+)/', $command, $matches)) {
            $this->formData['quantity'] = (int)$matches[1];
            return ['action' => 'field_filled', 'field' => 'quantity', 'value' => $this->formData['quantity']];
        }

        return ['action' => 'not_recognized', 'command' => $command];
    }

    private function parseDate(string $dateStr): string
    {
        // Logique simple de parsing de date - à améliorer selon besoins
        $dateStr = trim($dateStr);
        
        // Formats supportés: "demain", "lundi", "15 janvier", etc.
        if ($dateStr === 'demain') {
            return date('Y-m-d', strtotime('+1 day'));
        }
        
        if ($dateStr === 'aujourd\'hui') {
            return date('Y-m-d');
        }
        
        // Tentative de parsing direct
        $timestamp = strtotime($dateStr);
        if ($timestamp) {
            return date('Y-m-d', $timestamp);
        }
        
        return $dateStr; // Retourne la chaîne originale si non reconnue
    }

    /**
     * Retourne les données du formulaire
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return $this->formData;
    }

    public function getCurrentContext(): ?string
    {
        return $this->currentContext;
    }

    public function clearFormData(): void
    {
        $this->formData = [];
        $this->currentContext = null;
        $this->waitingForFieldValue = null;
    }

    public function isWaitingForValue(): bool
    {
        return $this->waitingForFieldValue !== null;
    }

    public function getWaitingField(): ?string
    {
        return $this->waitingForFieldValue;
    }

    // Commandes vocales exemples
    /**
     * Retourne les commandes disponibles selon le contexte
     * @return array<string, string>
     */
    public function getAvailableCommands(): array
    {
        $commands = [];
        
        if ($this->currentContext === 'salle') {
            $commands = [
                'nom' => 'Dis "nom" puis donne la valeur',
                'capacité' => 'Dis "capacité" puis donne le nombre',
                'bâtiment' => 'Dis "bâtiment" puis donne le nom',
                'étage' => 'Dis "étage" puis donne le numéro',
                'statut' => 'Dis "statut" puis donne l\'état'
            ];
        } elseif ($this->currentContext === 'equipement') {
            $commands = [
                'nom' => 'Dis "nom" puis donne le nom de l\'équipement',
                'type' => 'Dis "type" puis donne le type',
                'quantité' => 'Dis "quantité" puis donne le nombre',
                'statut' => 'Dis "statut" puis donne l\'état'
            ];
        } elseif ($this->currentContext === 'reservation') {
            $commands = [
                'type' => 'Dis "type" puis "salle" ou "équipement"',
                'début' => 'Dis "début" puis la date',
                'fin' => 'Dis "fin" puis la date',
                'quantité' => 'Dis "quantité" puis le nombre'
            ];
        }
        
        return $commands;
    }
}