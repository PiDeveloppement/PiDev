<?php

namespace App\Service\Event;

use App\Entity\Event\Event;

class EventManager
{
    public function validate(Event $event): bool
    {
        if (empty($event->getTitle())) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        if (empty($event->getLocation())) {
            throw new \InvalidArgumentException('Le lieu est obligatoire');
        }

        if ($event->getCapacity() !== null && $event->getCapacity() <= 0) {
            throw new \InvalidArgumentException('La capacité doit être supérieure à 0');
        }

        if ($event->getTicketPrice() !== null && $event->getTicketPrice() < 0) {
            throw new \InvalidArgumentException('Le prix ne peut pas être négatif');
        }

        if ($event->getStartDate() !== null && $event->getEndDate() !== null) {
            if ($event->getEndDate() <= $event->getStartDate()) {
                throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début');
            }
        }

        if ($event->isFree() === true && $event->getTicketPrice() > 0) {
            throw new \InvalidArgumentException('Un événement gratuit ne peut pas avoir un prix');
        }

        return true;
    }
}