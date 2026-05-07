<?php

namespace App\Tests;

use App\Entity\Event\Event;
use App\Service\Event\EventManager;
use PHPUnit\Framework\TestCase;

class EventManagerTest extends TestCase
{
    private EventManager $manager;

    protected function setUp(): void
    {
        $this->manager = new EventManager();
    }

    public function testValidEvent(): void
    {
        $event = new Event();
        $event->setTitle('Conférence IA');
        $event->setLocation('ESPRIT');
        $event->setCapacity(100);
        $event->setTicketPrice(30.0);
        $event->setIsFree(false);
        $event->setStartDate(new \DateTimeImmutable('2026-06-01 09:00'));
        $event->setEndDate(new \DateTimeImmutable('2026-06-01 17:00'));

        $this->assertTrue($this->manager->validate($event));
    }

    public function testEventWithoutTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $event = new Event();
        $event->setTitle('');
        $event->setLocation('ESPRIT');

        $this->manager->validate($event);
    }

    public function testEventWithoutLocation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le lieu est obligatoire');

        $event = new Event();
        $event->setTitle('Conférence IA');
        $event->setLocation('');

        $this->manager->validate($event);
    }

    public function testEventWithNegativeCapacity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La capacité doit être supérieure à 0');

        $event = new Event();
        $event->setTitle('Conférence IA');
        $event->setLocation('ESPRIT');
        $event->setCapacity(-10);

        $this->manager->validate($event);
    }

    public function testEventWithNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix ne peut pas être négatif');

        $event = new Event();
        $event->setTitle('Conférence IA');
        $event->setLocation('ESPRIT');
        $event->setCapacity(100);
        $event->setTicketPrice(-5.0);

        $this->manager->validate($event);
    }

    public function testEventEndDateBeforeStartDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être postérieure à la date de début');

        $event = new Event();
        $event->setTitle('Conférence IA');
        $event->setLocation('ESPRIT');
        $event->setStartDate(new \DateTimeImmutable('2026-06-01 17:00'));
        $event->setEndDate(new \DateTimeImmutable('2026-06-01 09:00'));

        $this->manager->validate($event);
    }

    public function testFreeEventWithPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un événement gratuit ne peut pas avoir un prix');

        $event = new Event();
        $event->setTitle('Conférence IA');
        $event->setLocation('ESPRIT');
        $event->setIsFree(true);
        $event->setTicketPrice(30.0);

        $this->manager->validate($event);
    }
}