<?php

namespace App\Tests\Service\Resource;

use App\Entity\Resource\ReservationResource;
use App\Entity\Resource\Salle;
use App\Entity\Resource\Equipement;
use App\Entity\Event\Event;
use PHPUnit\Framework\TestCase;

class ReservationResourceTest extends TestCase
{
    private ReservationResource $reservationResource;

    protected function setUp(): void
    {
        $this->reservationResource = new ReservationResource();
    }

    public function testConstructorSetsDefaultDates(): void
    {
        $this->assertNotNull($this->reservationResource->getStartTime());
        $this->assertNotNull($this->reservationResource->getEndTime());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->reservationResource->getStartTime());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->reservationResource->getEndTime());
        $this->assertGreaterThan($this->reservationResource->getStartTime(), $this->reservationResource->getEndTime());
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->reservationResource->getId());
        $this->assertNull($this->reservationResource->getResourceType());
        $this->assertNull($this->reservationResource->getSalle());
        $this->assertNull($this->reservationResource->getEquipement());
        $this->assertNull($this->reservationResource->getEvent());
        $this->assertEquals(1, $this->reservationResource->getQuantity());
        $this->assertNull($this->reservationResource->getRemainingQuantity());
    }

    public function testSetAndGetResourceType(): void
    {
        $resourceType = 'SALLE';
        $this->reservationResource->setResourceType($resourceType);
        $this->assertEquals($resourceType, $this->reservationResource->getResourceType());

        $resourceType = 'EQUIPEMENT';
        $this->reservationResource->setResourceType($resourceType);
        $this->assertEquals($resourceType, $this->reservationResource->getResourceType());
    }

    public function testSetAndGetSalle(): void
    {
        $salle = $this->createMock(Salle::class);
        $this->reservationResource->setSalle($salle);
        $this->assertEquals($salle, $this->reservationResource->getSalle());

        $this->reservationResource->setSalle(null);
        $this->assertNull($this->reservationResource->getSalle());
    }

    public function testSetAndGetEquipement(): void
    {
        $equipement = $this->createMock(Equipement::class);
        $this->reservationResource->setEquipement($equipement);
        $this->assertEquals($equipement, $this->reservationResource->getEquipement());

        $this->reservationResource->setEquipement(null);
        $this->assertNull($this->reservationResource->getEquipement());
    }

    public function testSetAndGetEvent(): void
    {
        $event = $this->createMock(Event::class);
        $this->reservationResource->setEvent($event);
        $this->assertEquals($event, $this->reservationResource->getEvent());

        $this->reservationResource->setEvent(null);
        $this->assertNull($this->reservationResource->getEvent());
    }

    public function testSetAndGetStartTime(): void
    {
        $startTime = new \DateTimeImmutable('2023-06-01 10:00:00');
        $this->reservationResource->setStartTime($startTime);
        $this->assertEquals($startTime, $this->reservationResource->getStartTime());
    }

    public function testSetAndGetEndTime(): void
    {
        $endTime = new \DateTimeImmutable('2023-06-01 18:00:00');
        $this->reservationResource->setEndTime($endTime);
        $this->assertEquals($endTime, $this->reservationResource->getEndTime());
    }

    public function testSetAndGetQuantity(): void
    {
        $quantity = 5;
        $this->reservationResource->setQuantity($quantity);
        $this->assertEquals($quantity, $this->reservationResource->getQuantity());

        $quantity = 10;
        $this->reservationResource->setQuantity($quantity);
        $this->assertEquals($quantity, $this->reservationResource->getQuantity());
    }

    public function testSetAndGetRemainingQuantity(): void
    {
        $remainingQuantity = 3;
        $this->reservationResource->setRemainingQuantity($remainingQuantity);
        $this->assertEquals($remainingQuantity, $this->reservationResource->getRemainingQuantity());
    }

    public function testFluentInterfaceOnSetters(): void
    {
        $salle = $this->createMock(Salle::class);
        $equipement = $this->createMock(Equipement::class);
        $event = $this->createMock(Event::class);
        $startTime = new \DateTimeImmutable('2023-06-01 10:00:00');
        $endTime = new \DateTimeImmutable('2023-06-01 18:00:00');

        $result = $this->reservationResource
            ->setResourceType('SALLE')
            ->setSalle($salle)
            ->setEquipement($equipement)
            ->setEvent($event)
            ->setStartTime($startTime)
            ->setEndTime($endTime)
            ->setQuantity(5)
            ->setRemainingQuantity(3);

        $this->assertSame($this->reservationResource, $result);
    }

    public function testCompleteReservationSetup(): void
    {
        $salle = $this->createMock(Salle::class);
        $event = $this->createMock(Event::class);
        $startTime = new \DateTimeImmutable('2023-06-01 09:00:00');
        $endTime = new \DateTimeImmutable('2023-06-01 17:00:00');

        $this->reservationResource
            ->setResourceType('SALLE')
            ->setSalle($salle)
            ->setEvent($event)
            ->setStartTime($startTime)
            ->setEndTime($endTime)
            ->setQuantity(1)
            ->setRemainingQuantity(1);

        $this->assertEquals('SALLE', $this->reservationResource->getResourceType());
        $this->assertEquals($salle, $this->reservationResource->getSalle());
        $this->assertEquals($event, $this->reservationResource->getEvent());
        $this->assertEquals($startTime, $this->reservationResource->getStartTime());
        $this->assertEquals($endTime, $this->reservationResource->getEndTime());
        $this->assertEquals(1, $this->reservationResource->getQuantity());
        $this->assertEquals(1, $this->reservationResource->getRemainingQuantity());
        $this->assertNull($this->reservationResource->getEquipement());
    }

    public function testEquipmentReservationSetup(): void
    {
        $equipement = $this->createMock(Equipement::class);
        $event = $this->createMock(Event::class);
        $startTime = new \DateTimeImmutable('2023-06-02 14:00:00');
        $endTime = new \DateTimeImmutable('2023-06-02 16:00:00');

        $this->reservationResource
            ->setResourceType('EQUIPEMENT')
            ->setEquipement($equipement)
            ->setEvent($event)
            ->setStartTime($startTime)
            ->setEndTime($endTime)
            ->setQuantity(3)
            ->setRemainingQuantity(2);

        $this->assertEquals('EQUIPEMENT', $this->reservationResource->getResourceType());
        $this->assertEquals($equipement, $this->reservationResource->getEquipement());
        $this->assertEquals($event, $this->reservationResource->getEvent());
        $this->assertEquals($startTime, $this->reservationResource->getStartTime());
        $this->assertEquals($endTime, $this->reservationResource->getEndTime());
        $this->assertEquals(3, $this->reservationResource->getQuantity());
        $this->assertEquals(2, $this->reservationResource->getRemainingQuantity());
        $this->assertNull($this->reservationResource->getSalle());
    }

    public function testDateValidation(): void
    {
        $startTime = new \DateTimeImmutable('2023-06-01 10:00:00');
        $endTime = new \DateTimeImmutable('2023-06-01 18:00:00');

        $this->reservationResource->setStartTime($startTime);
        $this->reservationResource->setEndTime($endTime);

        $this->assertLessThan($this->reservationResource->getEndTime(), $this->reservationResource->getStartTime());
    }

    public function testQuantityRange(): void
    {
        // Test minimum quantity
        $this->reservationResource->setQuantity(1);
        $this->assertEquals(1, $this->reservationResource->getQuantity());

        // Test maximum reasonable quantity
        $this->reservationResource->setQuantity(100);
        $this->assertEquals(100, $this->reservationResource->getQuantity());
    }

    public function testResourceTypeChoices(): void
    {
        $validTypes = ['SALLE', 'EQUIPEMENT'];
        
        foreach ($validTypes as $type) {
            $this->reservationResource->setResourceType($type);
            $this->assertEquals($type, $this->reservationResource->getResourceType());
        }
    }
}
