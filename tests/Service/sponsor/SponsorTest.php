<?php

namespace App\Tests\Service\Sponsor;

use App\Entity\Sponsor\Sponsor;
use App\Entity\User\UserModel;
use PHPUnit\Framework\TestCase;

class SponsorTest extends TestCase
{
    private Sponsor $sponsor;

    protected function setUp(): void
    {
        $this->sponsor = new Sponsor();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->sponsor->getId());
        $this->assertNull($this->sponsor->getEventId());
        $this->assertNull($this->sponsor->getUser());
        $this->assertNull($this->sponsor->getCompanyName());
        $this->assertNull($this->sponsor->getContactEmail());
        $this->assertNull($this->sponsor->getLogoUrl());
        $this->assertEquals('', $this->sponsor->getContributionName());
        $this->assertEquals(0.0, $this->sponsor->getContributionAmount());
        $this->assertNull($this->sponsor->getContractUrl());
        $this->assertNull($this->sponsor->getAccessCode());
        $this->assertNull($this->sponsor->getIndustry());
        $this->assertNull($this->sponsor->getPhone());
        $this->assertNull($this->sponsor->getDocumentUrl());
        $this->assertNull($this->sponsor->getTaxId());
    }

    public function testSetAndGetEventId(): void
    {
        $eventId = 123;
        $this->sponsor->setEventId($eventId);
        $this->assertEquals($eventId, $this->sponsor->getEventId());

        $this->sponsor->setEventId(null);
        $this->assertNull($this->sponsor->getEventId());
    }

    public function testSetAndGetUser(): void
    {
        $user = $this->createMock(UserModel::class);
        $this->sponsor->setUser($user);
        $this->assertEquals($user, $this->sponsor->getUser());

        $this->sponsor->setUser(null);
        $this->assertNull($this->sponsor->getUser());
    }

    public function testSetAndGetCompanyName(): void
    {
        $companyName = 'Tech Corp';
        $this->sponsor->setCompanyName($companyName);
        $this->assertEquals($companyName, $this->sponsor->getCompanyName());

        // Test with whitespace trimming
        $companyName = '  Innovate Inc  ';
        $this->sponsor->setCompanyName($companyName);
        $this->assertEquals('Innovate Inc', $this->sponsor->getCompanyName());

        $this->sponsor->setCompanyName(null);
        $this->assertNull($this->sponsor->getCompanyName());
    }

    public function testSetAndGetContactEmail(): void
    {
        $email = 'contact@company.com';
        $this->sponsor->setContactEmail($email);
        $this->assertEquals($email, $this->sponsor->getContactEmail());

        // Test with whitespace trimming
        $email = '  info@business.com  ';
        $this->sponsor->setContactEmail($email);
        $this->assertEquals('info@business.com', $this->sponsor->getContactEmail());

        $this->sponsor->setContactEmail(null);
        $this->assertNull($this->sponsor->getContactEmail());
    }

    public function testSetAndGetLogoUrl(): void
    {
        $logoUrl = 'https://example.com/logo.png';
        $this->sponsor->setLogoUrl($logoUrl);
        $this->assertEquals($logoUrl, $this->sponsor->getLogoUrl());

        // Test with empty string
        $this->sponsor->setLogoUrl('');
        $this->assertNull($this->sponsor->getLogoUrl());

        // Test with whitespace trimming
        $logoUrl = '  https://company.com/logo.jpg  ';
        $this->sponsor->setLogoUrl($logoUrl);
        $this->assertEquals('https://company.com/logo.jpg', $this->sponsor->getLogoUrl());

        $this->sponsor->setLogoUrl(null);
        $this->assertNull($this->sponsor->getLogoUrl());
    }

    public function testSetAndGetContributionName(): void
    {
        $contribution = 1500.50;
        $this->sponsor->setContributionName($contribution);
        $this->assertEquals('1500.50', $this->sponsor->getContributionName());
        $this->assertEquals(1500.50, $this->sponsor->getContributionAmount());

        // Test with string
        $contribution = '2500.75';
        $this->sponsor->setContributionName($contribution);
        $this->assertEquals('2500.75', $this->sponsor->getContributionName());
        $this->assertEquals(2500.75, $this->sponsor->getContributionAmount());

        // Test with null
        $this->sponsor->setContributionName(null);
        $this->assertEquals('', $this->sponsor->getContributionName());
        $this->assertEquals(0.0, $this->sponsor->getContributionAmount());

        // Test with empty string
        $this->sponsor->setContributionName('');
        $this->assertEquals('', $this->sponsor->getContributionName());
        $this->assertEquals(0.0, $this->sponsor->getContributionAmount());
    }

    public function testSetAndGetContractUrl(): void
    {
        $contractUrl = 'https://example.com/contract.pdf';
        $this->sponsor->setContractUrl($contractUrl);
        $this->assertEquals($contractUrl, $this->sponsor->getContractUrl());

        // Test with empty string
        $this->sponsor->setContractUrl('');
        $this->assertNull($this->sponsor->getContractUrl());

        $this->sponsor->setContractUrl(null);
        $this->assertNull($this->sponsor->getContractUrl());
    }

    public function testSetAndGetAccessCode(): void
    {
        $accessCode = 'SPONSOR123';
        $this->sponsor->setAccessCode($accessCode);
        $this->assertEquals($accessCode, $this->sponsor->getAccessCode());

        // Test with empty string
        $this->sponsor->setAccessCode('');
        $this->assertNull($this->sponsor->getAccessCode());

        $this->sponsor->setAccessCode(null);
        $this->assertNull($this->sponsor->getAccessCode());
    }

    public function testSetAndGetIndustry(): void
    {
        $industry = 'Technology';
        $this->sponsor->setIndustry($industry);
        $this->assertEquals($industry, $this->sponsor->getIndustry());

        // Test with empty string
        $this->sponsor->setIndustry('');
        $this->assertNull($this->sponsor->getIndustry());

        $this->sponsor->setIndustry(null);
        $this->assertNull($this->sponsor->getIndustry());
    }

    public function testSetAndGetPhone(): void
    {
        $phone = '21612345678';
        $this->sponsor->setPhone($phone);
        $this->assertEquals($phone, $this->sponsor->getPhone());

        // Test with normalization (removes non-digits)
        $phone = '216-123-456-78';
        $this->sponsor->setPhone($phone);
        $this->assertEquals('21612345678', $this->sponsor->getPhone());

        // Test with empty string
        $this->sponsor->setPhone('');
        $this->assertNull($this->sponsor->getPhone());

        $this->sponsor->setPhone(null);
        $this->assertNull($this->sponsor->getPhone());
    }

    public function testSetAndGetDocumentUrl(): void
    {
        $documentUrl = 'https://example.com/document.pdf';
        $this->sponsor->setDocumentUrl($documentUrl);
        $this->assertEquals($documentUrl, $this->sponsor->getDocumentUrl());

        // Test with empty string
        $this->sponsor->setDocumentUrl('');
        $this->assertNull($this->sponsor->getDocumentUrl());

        $this->sponsor->setDocumentUrl(null);
        $this->assertNull($this->sponsor->getDocumentUrl());
    }

    public function testSetAndGetTaxId(): void
    {
        $taxId = '1234567A';
        $this->sponsor->setTaxId($taxId);
        $this->assertEquals($taxId, $this->sponsor->getTaxId());

        // Test with uppercase conversion
        $taxId = '1234567a';
        $this->sponsor->setTaxId($taxId);
        $this->assertEquals('1234567A', $this->sponsor->getTaxId());

        // Test with whitespace trimming
        $taxId = '  7654321B  ';
        $this->sponsor->setTaxId($taxId);
        $this->assertEquals('7654321B', $this->sponsor->getTaxId());

        // Test with empty string
        $this->sponsor->setTaxId('');
        $this->assertNull($this->sponsor->getTaxId());

        $this->sponsor->setTaxId(null);
        $this->assertNull($this->sponsor->getTaxId());
    }

    public function testFluentInterfaceOnSetters(): void
    {
        $user = $this->createMock(UserModel::class);

        $result = $this->sponsor
            ->setEventId(123)
            ->setUser($user)
            ->setCompanyName('Test Company')
            ->setContactEmail('test@example.com')
            ->setLogoUrl('https://example.com/logo.png')
            ->setContributionName(1500.00)
            ->setContractUrl('https://example.com/contract.pdf')
            ->setAccessCode('ACCESS123')
            ->setIndustry('Technology')
            ->setPhone('21612345678')
            ->setDocumentUrl('https://example.com/doc.pdf')
            ->setTaxId('1234567A');

        $this->assertSame($this->sponsor, $result);
    }

    public function testCompleteSponsorSetup(): void
    {
        $user = $this->createMock(UserModel::class);

        $this->sponsor
            ->setEventId(456)
            ->setUser($user)
            ->setCompanyName('Global Solutions')
            ->setContactEmail('contact@globalsolutions.com')
            ->setLogoUrl('https://globalsolutions.com/logo.png')
            ->setContributionName(5000.00)
            ->setContractUrl('https://globalsolutions.com/contract.pdf')
            ->setAccessCode('GLOBAL2023')
            ->setIndustry('Software Development')
            ->setPhone('21698765432')
            ->setDocumentUrl('https://globalsolutions.com/docs.pdf')
            ->setTaxId('9876543Z');

        $this->assertEquals(456, $this->sponsor->getEventId());
        $this->assertEquals($user, $this->sponsor->getUser());
        $this->assertEquals('Global Solutions', $this->sponsor->getCompanyName());
        $this->assertEquals('contact@globalsolutions.com', $this->sponsor->getContactEmail());
        $this->assertEquals('https://globalsolutions.com/logo.png', $this->sponsor->getLogoUrl());
        $this->assertEquals('5000.00', $this->sponsor->getContributionName());
        $this->assertEquals(5000.00, $this->sponsor->getContributionAmount());
        $this->assertEquals('https://globalsolutions.com/contract.pdf', $this->sponsor->getContractUrl());
        $this->assertEquals('GLOBAL2023', $this->sponsor->getAccessCode());
        $this->assertEquals('Software Development', $this->sponsor->getIndustry());
        $this->assertEquals('21698765432', $this->sponsor->getPhone());
        $this->assertEquals('https://globalsolutions.com/docs.pdf', $this->sponsor->getDocumentUrl());
        $this->assertEquals('9876543Z', $this->sponsor->getTaxId());
    }

    public function testSponsorWithMinimalData(): void
    {
        $this->sponsor
            ->setEventId(789)
            ->setCompanyName('Minimal Corp')
            ->setContactEmail('info@minimal.com')
            ->setContributionName(1000.00)
            ->setPhone('21611111111')
            ->setTaxId('1111111A');

        $this->assertEquals(789, $this->sponsor->getEventId());
        $this->assertEquals('Minimal Corp', $this->sponsor->getCompanyName());
        $this->assertEquals('info@minimal.com', $this->sponsor->getContactEmail());
        $this->assertEquals('1000.00', $this->sponsor->getContributionName());
        $this->assertEquals(1000.00, $this->sponsor->getContributionAmount());
        $this->assertEquals('21611111111', $this->sponsor->getPhone());
        $this->assertEquals('1111111A', $this->sponsor->getTaxId());
        $this->assertNull($this->sponsor->getUser());
        $this->assertNull($this->sponsor->getLogoUrl());
        $this->assertNull($this->sponsor->getContractUrl());
        $this->assertNull($this->sponsor->getAccessCode());
        $this->assertNull($this->sponsor->getIndustry());
        $this->assertNull($this->sponsor->getDocumentUrl());
    }

    public function testContributionAmountConversion(): void
    {
        // Test various contribution formats
        $contributions = [1000, '1500.50', 2000.75, '3000'];

        foreach ($contributions as $contribution) {
            $this->sponsor->setContributionName($contribution);
            $expectedAmount = (float) $contribution;
            $this->assertEquals($expectedAmount, $this->sponsor->getContributionAmount());
        }
    }

    public function testPhoneNormalization(): void
    {
        $testCases = [
            '21612345678' => '21612345678',
            '216-123-456-78' => '21612345678',
            '216 123 456 78' => '21612345678',
            '(216) 123-45678' => '21612345678',
            '+21612345678' => '21612345678'
        ];

        foreach ($testCases as $input => $expected) {
            $this->sponsor->setPhone($input);
            $this->assertEquals($expected, $this->sponsor->getPhone());
        }
    }
}
