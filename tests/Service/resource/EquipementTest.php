<?php

namespace App\Tests\Service\Resource;

use App\Entity\Resource\Equipement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class EquipementTest extends TestCase
{
    private Equipement $equipement;

    protected function setUp(): void
    {
        $this->equipement = new Equipement();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->equipement->getId());
        $this->assertNull($this->equipement->getName());
        $this->assertNull($this->equipement->getEquipementType());
        $this->assertEquals('DISPONIBLE', $this->equipement->getStatus());
        $this->assertEquals(0, $this->equipement->getQuantity());
        $this->assertEquals(0, $this->equipement->getOriginalQuantity());
        $this->assertNull($this->equipement->getImagePath());
        $this->assertNull($this->equipement->getImageFile());
    }

    public function testSetAndGetName(): void
    {
        $name = 'Projecteur Epson';
        $this->equipement->setName($name);
        $this->assertEquals($name, $this->equipement->getName());
    }

    public function testSetAndGetEquipementType(): void
    {
        $equipementType = 'Vidéoprojecteur';
        $this->equipement->setEquipementType($equipementType);
        $this->assertEquals($equipementType, $this->equipement->getEquipementType());
    }

    public function testSetAndGetStatus(): void
    {
        $statuses = ['DISPONIBLE', 'INDISPONIBLE', 'MAINTENANCE'];
        
        foreach ($statuses as $status) {
            $this->equipement->setStatus($status);
            $this->assertEquals($status, $this->equipement->getStatus());
        }
    }

    public function testSetAndGetQuantity(): void
    {
        $quantity = 5;
        $this->equipement->setQuantity($quantity);
        $this->assertEquals($quantity, $this->equipement->getQuantity());

        // Test with zero
        $this->equipement->setQuantity(0);
        $this->assertEquals(0, $this->equipement->getQuantity());

        // Test with larger number
        $this->equipement->setQuantity(100);
        $this->assertEquals(100, $this->equipement->getQuantity());
    }

    public function testSetAndGetOriginalQuantity(): void
    {
        $originalQuantity = 10;
        $this->equipement->setOriginalQuantity($originalQuantity);
        $this->assertEquals($originalQuantity, $this->equipement->getOriginalQuantity());
    }

    public function testSetAndGetImagePath(): void
    {
        $imagePath = 'images/equipements/projecteur.jpg';
        $this->equipement->setImagePath($imagePath);
        $this->assertEquals($imagePath, $this->equipement->getImagePath());

        $this->equipement->setImagePath(null);
        $this->assertNull($this->equipement->getImagePath());
    }

    public function testSetAndGetImageFile(): void
    {
        $file = $this->createMock(File::class);
        $this->equipement->setImageFile($file);
        $this->assertEquals($file, $this->equipement->getImageFile());

        $this->equipement->setImageFile(null);
        $this->assertNull($this->equipement->getImageFile());
    }

    public function testFluentInterfaceOnSetters(): void
    {
        $file = $this->createMock(File::class);

        $result = $this->equipement
            ->setName('Microphone sans fil')
            ->setEquipementType('Audio')
            ->setStatus('DISPONIBLE')
            ->setQuantity(3)
            ->setOriginalQuantity(5)
            ->setImagePath('images/microphone.jpg');

        // setImageFile returns void, so we set it separately
        $this->equipement->setImageFile($file);

        $this->assertSame($this->equipement, $result);
    }

    public function testCompleteEquipementSetup(): void
    {
        $file = $this->createMock(File::class);

        $this->equipement
            ->setName('Tableau blanc interactif')
            ->setEquipementType('Affichage')
            ->setStatus('DISPONIBLE')
            ->setQuantity(2)
            ->setOriginalQuantity(2)
            ->setImagePath('images/tableau.jpg')
            ->setImageFile($file);

        $this->assertEquals('Tableau blanc interactif', $this->equipement->getName());
        $this->assertEquals('Affichage', $this->equipement->getEquipementType());
        $this->assertEquals('DISPONIBLE', $this->equipement->getStatus());
        $this->assertEquals(2, $this->equipement->getQuantity());
        $this->assertEquals(2, $this->equipement->getOriginalQuantity());
        $this->assertEquals('images/tableau.jpg', $this->equipement->getImagePath());
        $this->assertEquals($file, $this->equipement->getImageFile());
    }

    public function testStatusTransitions(): void
    {
        // Start with available
        $this->assertEquals('DISPONIBLE', $this->equipement->getStatus());

        // Mark as under maintenance
        $this->equipement->setStatus('MAINTENANCE');
        $this->assertEquals('MAINTENANCE', $this->equipement->getStatus());

        // Mark as unavailable
        $this->equipement->setStatus('INDISPONIBLE');
        $this->assertEquals('INDISPONIBLE', $this->equipement->getStatus());

        // Back to available
        $this->equipement->setStatus('DISPONIBLE');
        $this->assertEquals('DISPONIBLE', $this->equipement->getStatus());
    }

    public function testQuantityTracking(): void
    {
        // Set original quantity
        $this->equipement->setOriginalQuantity(10);
        $this->assertEquals(10, $this->equipement->getOriginalQuantity());

        // Set current quantity (less than original)
        $this->equipement->setQuantity(7);
        $this->assertEquals(7, $this->equipement->getQuantity());
        $this->assertEquals(10, $this->equipement->getOriginalQuantity());

        // Restore to original quantity
        $this->equipement->setQuantity(10);
        $this->assertEquals(10, $this->equipement->getQuantity());
        $this->assertEquals(10, $this->equipement->getOriginalQuantity());
    }

    public function testEquipementWithoutImage(): void
    {
        $this->equipement
            ->setName('Ordinateur portable')
            ->setEquipementType('Informatique')
            ->setStatus('DISPONIBLE')
            ->setQuantity(5)
            ->setOriginalQuantity(5);

        $this->assertEquals('Ordinateur portable', $this->equipement->getName());
        $this->assertEquals('Informatique', $this->equipement->getEquipementType());
        $this->assertEquals('DISPONIBLE', $this->equipement->getStatus());
        $this->assertEquals(5, $this->equipement->getQuantity());
        $this->assertEquals(5, $this->equipement->getOriginalQuantity());
        $this->assertNull($this->equipement->getImagePath());
        $this->assertNull($this->equipement->getImageFile());
    }

    public function testEquipementTypes(): void
    {
        $types = [
            'Vidéoprojecteur',
            'Ordinateur',
            'Microphone',
            'Système audio',
            'Tableau interactif',
            'Caméra',
            'Écran',
            'Câbles et connectiques'
        ];

        foreach ($types as $type) {
            $this->equipement->setEquipementType($type);
            $this->assertEquals($type, $this->equipement->getEquipementType());
        }
    }

    public function testQuantityValidation(): void
    {
        // Test minimum quantity
        $this->equipement->setQuantity(1);
        $this->assertEquals(1, $this->equipement->getQuantity());

        // Test maximum reasonable quantity
        $this->equipement->setQuantity(1000);
        $this->assertEquals(1000, $this->equipement->getQuantity());

        // Test original quantity tracking
        $this->equipement->setOriginalQuantity(50);
        $this->assertEquals(50, $this->equipement->getOriginalQuantity());
    }

    public function testImageManagement(): void
    {
        // Test without image initially
        $this->assertNull($this->equipement->getImagePath());
        $this->assertNull($this->equipement->getImageFile());

        // Set image path only
        $imagePath = 'uploads/equipements/test.jpg';
        $this->equipement->setImagePath($imagePath);
        $this->assertEquals($imagePath, $this->equipement->getImagePath());
        $this->assertNull($this->equipement->getImageFile());

        // Set image file
        $file = $this->createMock(File::class);
        $this->equipement->setImageFile($file);
        $this->assertEquals($file, $this->equipement->getImageFile());
        $this->assertEquals($imagePath, $this->equipement->getImagePath());

        // Remove image file but keep path
        $this->equipement->setImageFile(null);
        $this->assertNull($this->equipement->getImageFile());
        $this->assertEquals($imagePath, $this->equipement->getImagePath());
    }
}
