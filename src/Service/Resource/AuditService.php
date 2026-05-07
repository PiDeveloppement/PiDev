<?php

namespace App\Service\Resource;

use App\Entity\Resource\ReservationResource;
use App\Entity\Resource\Salle;
use App\Entity\Resource\Equipement;
use App\Entity\User\UserModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditService
{
    private EntityManagerInterface $em;
    private RequestStack $requestStack;
    private Security $security;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack, Security $security)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->security = $security;
    }

    public function logCreate(object $resource, ?UserModel $user = null): void
    {
        $this->createLog('CREATE', $resource, null, null, $user);
    }

    /**
     * Log une mise à jour de ressource
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function logUpdate(object $resource, ?array $oldValues = null, ?array $newValues = null, ?UserModel $user = null): void
    {
        $this->createLog('UPDATE', $resource, $oldValues, $newValues, $user);
    }

    public function logDelete(object $resource, ?UserModel $user = null): void
    {
        $this->createLog('DELETE', $resource, null, null, $user);
    }

    /**
     * Crée une entrée de log d'audit
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    private function createLog(string $action, object $resource, ?array $oldValues = null, ?array $newValues = null, ?UserModel $user = null): void
    {
        // Déterminer le type et les détails de la ressource
        $resourceType = $this->getResourceType($resource);
        $resourceId = method_exists($resource, 'getId') ? $resource->getId() : null;
        $resourceName = $this->getResourceName($resource);

        // Définir l'utilisateur
        $userId = null;
        if ($user) {
            $userId = $user->getId();
        } else {
            $currentUser = $this->security->getUser();
            if ($currentUser instanceof UserModel) {
                $userId = $currentUser->getId();
            }
        }

        // Capturer les informations de la requête
        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request ? $request->getClientIp() : null;
        $userAgent = $request ? $request->headers->get('User-Agent') : null;

        // Insérer directement dans la table historique_logs avec SQL
        $sql = "INSERT INTO historique_logs (user_id, action, resource_type, resource_id, resource_name, old_values, new_values, created_at, ip_address, user_agent) 
                VALUES (:user_id, :action, :resource_type, :resource_id, :resource_name, :old_values, :new_values, :created_at, :ip_address, :user_agent)";

        $this->em->getConnection()->executeStatement($sql, [
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_name' => $resourceName,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    private function getResourceType(object $resource): string
    {
        if ($resource instanceof ReservationResource) {
            return 'RESERVATION';
        } elseif ($resource instanceof Salle) {
            return 'SALLE';
        } elseif ($resource instanceof Equipement) {
            return 'EQUIPEMENT';
        }
        
        return 'UNKNOWN';
    }

    private function getResourceName(object $resource): string
    {
        if ($resource instanceof ReservationResource) {
            $eventName = $resource->getEvent()?->getTitle() ?? 'Événement inconnu';
            $resourceName = $resource->getResourceType();
            if ($resource->getSalle()) {
                $resourceName .= ' - ' . $resource->getSalle()->getName();
            }
            if ($resource->getEquipement()) {
                $resourceName .= ' - ' . $resource->getEquipement()->getName();
            }
            return 'Réservation: ' . $eventName . ' - ' . $resourceName;
        } elseif ($resource instanceof Salle) {
            return $resource->getName() ?? 'Salle sans nom';
        } elseif ($resource instanceof Equipement) {
            return 'Équipement ' . ($resource->getName() ?? 'sans nom');
        }
        
        return 'Ressource inconnue';
    }

    /**
     * Extrait les valeurs d'une entité sous forme de tableau
     * @return array<string, mixed>
     */
    public function extractEntityValues(object $entity): array
    {
        $values = [];
        $reflection = new \ReflectionClass($entity);
        
        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();
            
            // Ignorer les propriétés qui ne sont pas des colonnes de base de données
            if (in_array($propertyName, ['id', 'createdAt', 'updatedAt'])) {
                continue;
            }
            
            $getter = 'get' . ucfirst($propertyName);
            if (method_exists($entity, $getter)) {
                $value = $entity->$getter();
                
                // Convertir les objets en chaînes si nécessaire
                if (is_object($value)) {
                    if (method_exists($value, '__toString')) {
                        $value = (string) $value;
                    } elseif (method_exists($value, 'getId')) {
                        $value = $value->getId();
                    } else {
                        $value = get_class($value);
                    }
                }
                
                $values[$propertyName] = $value;
            }
        }
        
        return $values;
    }

    /**
     * Compare deux tableaux et retourne les différences
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChangedValues(array $oldValues, array $newValues): array
    {
        $changes = [];
        
        foreach ($oldValues as $key => $oldValue) {
            if (isset($newValues[$key]) && $oldValue !== $newValues[$key]) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValues[$key]
                ];
            }
        }
        
        // Vérifier les nouvelles clés qui n'existaient pas avant
        foreach ($newValues as $key => $newValue) {
            if (!isset($oldValues[$key])) {
                $changes[$key] = [
                    'old' => null,
                    'new' => $newValue
                ];
            }
        }
        
        return $changes;
    }
}