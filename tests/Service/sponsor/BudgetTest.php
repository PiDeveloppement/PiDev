<?php

namespace App\Tests\Service\Sponsor;

use App\Entity\Budget\Budget;
use PHPUnit\Framework\TestCase;

class BudgetTest extends TestCase
{
    private Budget $budget;

    protected function setUp(): void
    {
        $this->budget = new Budget();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->budget->getId());
        $this->assertNull($this->budget->getEventId());
        $this->assertEquals('', $this->budget->getInitialBudget());
        $this->assertEquals('0.00', $this->budget->getTotalExpenses());
        $this->assertEquals('', $this->budget->getTotalRevenue());
        $this->assertEquals('0.00', $this->budget->getRentabilite());
    }

    public function testSetAndGetEventId(): void
    {
        $eventId = 123;
        $this->budget->setEventId($eventId);
        $this->assertEquals($eventId, $this->budget->getEventId());

        $this->budget->setEventId(null);
        $this->assertNull($this->budget->getEventId());
    }

    public function testSetAndGetInitialBudget(): void
    {
        $initialBudget = 10000.50;
        $this->budget->setInitialBudget($initialBudget);
        $this->assertEquals('10000.50', $this->budget->getInitialBudget());

        // Test with string
        $initialBudget = '15000.75';
        $this->budget->setInitialBudget($initialBudget);
        $this->assertEquals('15000.75', $this->budget->getInitialBudget());

        // Test with null
        $this->budget->setInitialBudget(null);
        $this->assertEquals('', $this->budget->getInitialBudget());

        // Test with empty string
        $this->budget->setInitialBudget('');
        $this->assertEquals('', $this->budget->getInitialBudget());

        // Test with zero
        $this->budget->setInitialBudget(0);
        $this->assertEquals('0.00', $this->budget->getInitialBudget());
    }

    public function testSetAndGetTotalExpenses(): void
    {
        $totalExpenses = 5000.25;
        $this->budget->setTotalExpenses($totalExpenses);
        $this->assertEquals('5000.25', $this->budget->getTotalExpenses());

        // Test with string
        $totalExpenses = '7500.50';
        $this->budget->setTotalExpenses($totalExpenses);
        $this->assertEquals('7500.50', $this->budget->getTotalExpenses());

        // Test with zero
        $this->budget->setTotalExpenses(0);
        $this->assertEquals('0.00', $this->budget->getTotalExpenses());

        // Test with negative (should be formatted as positive)
        $this->budget->setTotalExpenses(-100);
        $this->assertEquals('-100.00', $this->budget->getTotalExpenses());
    }

    public function testSetAndGetTotalRevenue(): void
    {
        $totalRevenue = 8000.75;
        $this->budget->setTotalRevenue($totalRevenue);
        $this->assertEquals('8000.75', $this->budget->getTotalRevenue());

        // Test with string
        $totalRevenue = '12000.00';
        $this->budget->setTotalRevenue($totalRevenue);
        $this->assertEquals('12000.00', $this->budget->getTotalRevenue());

        // Test with empty string
        $this->budget->setTotalRevenue('');
        $this->assertEquals('', $this->budget->getTotalRevenue());

        // Test with zero
        $this->budget->setTotalRevenue(0);
        $this->assertEquals('0.00', $this->budget->getTotalRevenue());
    }

    public function testSetAndGetRentabilite(): void
    {
        $rentabilite = 3000.25;
        $this->budget->setRentabilite($rentabilite);
        $this->assertEquals('3000.25', $this->budget->getRentabilite());

        // Test with string
        $rentabilite = '4500.50';
        $this->budget->setRentabilite($rentabilite);
        $this->assertEquals('4500.50', $this->budget->getRentabilite());

        // Test with negative (loss)
        $rentabilite = -1500.75;
        $this->budget->setRentabilite($rentabilite);
        $this->assertEquals('-1500.75', $this->budget->getRentabilite());

        // Test with zero
        $this->budget->setRentabilite(0);
        $this->assertEquals('0.00', $this->budget->getRentabilite());
    }

    public function testRefreshRentabilite(): void
    {
        // Set initial values
        $this->budget->setTotalRevenue('10000.00');
        $this->budget->setTotalExpenses('7000.00');

        // Refresh rentabilite
        $result = $this->budget->refreshRentabilite();
        
        // Expected: 10000 - 7000 = 3000
        $this->assertEquals('3000.00', $this->budget->getRentabilite());
        $this->assertSame($this->budget, $result); // Test fluent interface

        // Test with loss
        $this->budget->setTotalRevenue('5000.00');
        $this->budget->setTotalExpenses('8000.00');
        $this->budget->refreshRentabilite();
        
        // Expected: 5000 - 8000 = -3000
        $this->assertEquals('-3000.00', $this->budget->getRentabilite());

        // Test with zero values
        $this->budget->setTotalRevenue('0.00');
        $this->budget->setTotalExpenses('0.00');
        $this->budget->refreshRentabilite();
        
        // Expected: 0 - 0 = 0
        $this->assertEquals('0.00', $this->budget->getRentabilite());
    }

    public function testFluentInterfaceOnSetters(): void
    {
        $result = $this->budget
            ->setEventId(456)
            ->setInitialBudget(20000.00)
            ->setTotalExpenses(8000.50)
            ->setTotalRevenue(25000.75)
            ->setRentabilite(17000.25);

        $this->assertSame($this->budget, $result);
    }

    public function testCompleteBudgetSetup(): void
    {
        $this->budget
            ->setEventId(789)
            ->setInitialBudget(50000.00)
            ->setTotalExpenses(35000.50)
            ->setTotalRevenue(60000.00)
            ->refreshRentabilite();

        $this->assertEquals(789, $this->budget->getEventId());
        $this->assertEquals('50000.00', $this->budget->getInitialBudget());
        $this->assertEquals('35000.50', $this->budget->getTotalExpenses());
        $this->assertEquals('60000.00', $this->budget->getTotalRevenue());
        $this->assertEquals('24999.50', $this->budget->getRentabilite()); // 60000 - 35000.50
    }

    public function testBudgetWithMinimalData(): void
    {
        $this->budget
            ->setEventId(101)
            ->setInitialBudget(10000.00);

        $this->assertEquals(101, $this->budget->getEventId());
        $this->assertEquals('10000.00', $this->budget->getInitialBudget());
        $this->assertEquals('0.00', $this->budget->getTotalExpenses());
        $this->assertEquals('', $this->budget->getTotalRevenue());
        $this->assertEquals('0.00', $this->budget->getRentabilite());
    }

    public function testDecimalFormatting(): void
    {
        // Test basic decimal formatting functionality
        $testCases = [
            ['input' => '1000', 'expected' => '1000.00'],
            ['input' => '1500.5', 'expected' => '1500.50'],
            ['input' => '2000.75', 'expected' => '2000.75'],
            ['input' => '3000.333', 'expected' => '3000.33']
        ];

        foreach ($testCases as $case) {
            $this->budget->setInitialBudget($case['input']);
            $actual = $this->budget->getInitialBudget();
            
            // Verify the format is correct (2 decimal places)
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $actual);
            // Verify it matches the expected format
            $this->assertEquals($case['expected'], $actual);
        }
    }

    public function testProfitAndLossScenarios(): void
    {
        // Profit scenario
        $this->budget->setTotalRevenue('15000.00');
        $this->budget->setTotalExpenses('10000.00');
        $this->budget->refreshRentabilite();
        $this->assertEquals('5000.00', $this->budget->getRentabilite());

        // Break-even scenario
        $this->budget->setTotalRevenue('12000.00');
        $this->budget->setTotalExpenses('12000.00');
        $this->budget->refreshRentabilite();
        $this->assertEquals('0.00', $this->budget->getRentabilite());

        // Loss scenario
        $this->budget->setTotalRevenue('8000.00');
        $this->budget->setTotalExpenses('12000.00');
        $this->budget->refreshRentabilite();
        $this->assertEquals('-4000.00', $this->budget->getRentabilite());
    }

    public function testBudgetCalculations(): void
    {
        // Set up a complete budget scenario
        $initialBudget = 30000.00;
        $totalRevenue = 45000.00;
        $totalExpenses = 28000.00;

        $this->budget
            ->setEventId(202)
            ->setInitialBudget($initialBudget)
            ->setTotalRevenue($totalRevenue)
            ->setTotalExpenses($totalExpenses)
            ->refreshRentabilite();

        // Verify all values
        $this->assertEquals(202, $this->budget->getEventId());
        $this->assertEquals('30000.00', $this->budget->getInitialBudget());
        $this->assertEquals('28000.00', $this->budget->getTotalExpenses());
        $this->assertEquals('45000.00', $this->budget->getTotalRevenue());
        $this->assertEquals('17000.00', $this->budget->getRentabilite()); // 45000 - 28000

        // Test profit margin calculation (revenue - expenses)
        $profitMargin = (float) $this->budget->getTotalRevenue() - (float) $this->budget->getTotalExpenses();
        $this->assertEquals(17000.00, $profitMargin);
        $this->assertEquals($profitMargin, (float) $this->budget->getRentabilite());
    }
}
