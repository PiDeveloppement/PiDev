<?php

namespace App\Entity\Sponsor;

use App\Entity\User\UserModel;
use App\Repository\Sponsor\SponsorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SponsorRepository::class)]
#[ORM\Table(name: 'sponsor')]
class Sponsor
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'event_id', type: Types::INTEGER)]
    #[Assert\NotNull(message: 'Veuillez selectionner un evenement.')]
    #[Assert\Positive(message: 'L\'evenement est obligatoire.')]
    private ?int $eventId = null;

    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'Id_User', nullable: true, onDelete: 'SET NULL')]
    private ?UserModel $user = null;

    #[ORM\Column(name: 'company_name', length: 150)]
    #[Assert\NotBlank(message: 'Le nom de l\'entreprise est obligatoire.')]
    #[Assert\Length(min: 2, max: 150, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    #[Assert\Regex(pattern: '/^(?!.*\\d).+$/', message: 'Le nom de l\'entreprise ne doit pas contenir de chiffres.')]
    private ?string $companyName = null;

    #[ORM\Column(name: 'contact_email', length: 150)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Email invalide.')]
    private ?string $contactEmail = null;

    #[ORM\Column(name: 'logo_url', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'URL logo trop longue.')]
    #[Assert\Url(message: 'L\'URL du logo n\'est pas valide.')]
    private ?string $logoUrl = null;

    #[ORM\Column(name: 'contribution_name', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\NotBlank(message: 'La contribution est obligatoire.')]
    #[Assert\Positive(message: 'La contribution doit etre > 0.')]
    private string $contributionName = '';

    #[ORM\Column(name: 'contract_url', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'URL contrat trop longue.')]
    #[Assert\Url(message: 'L\'URL du contrat n\'est pas valide.')]
    private ?string $contractUrl = null;

    #[ORM\Column(name: 'access_code', length: 20, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'Code d\'acces trop long.')]
    private ?string $accessCode = null;

    #[ORM\Column(name: 'industry', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le secteur est trop long.')]
    #[Assert\Regex(
        pattern: '/^$|^[A-Za-zÀ-ÿ\\s\\-&,]+$/u',
        message: 'Le secteur doit contenir uniquement des lettres.'
    )]
    private ?string $industry = null;

    #[ORM\Column(name: 'phone', length: 20, nullable: true)]
    #[Assert\NotBlank(message: 'Le telephone est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^216[0-9]{8}$/',
        message: 'Le telephone doit contenir 11 chiffres et commencer par 216.'
    )]
    private ?string $phone = null;

    #[ORM\Column(name: 'document_url', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'URL justificatif trop longue.')]
    #[Assert\Url(message: 'L\'URL du justificatif n\'est pas valide.')]
    private ?string $documentUrl = null;

    #[ORM\Column(name: 'tax_id', length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Le code fiscal est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^[0-9]{7}[A-Z]$/',
        message: 'Le code fiscal doit contenir 7 chiffres suivis d une lettre majuscule (ex: 1234567A).'
    )]
    private ?string $taxId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    public function setEventId(?int $eventId): self
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getUser(): ?UserModel
    {
        return $this->user;
    }

    public function setUser(?UserModel $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): self
    {
        $this->companyName = $companyName !== null ? trim($companyName) : null;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $contactEmail !== null ? trim($contactEmail) : null;

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): self
    {
        $this->logoUrl = $logoUrl !== null && trim($logoUrl) !== '' ? trim($logoUrl) : null;

        return $this;
    }

    public function getContributionName(): string
    {
        return $this->contributionName;
    }

    public function setContributionName(float|string $contributionName): self
    {
        if (trim((string) $contributionName) === '') {
            $this->contributionName = '';

            return $this;
        }

        $value = (float) $contributionName;
        $this->contributionName = number_format($value, 2, '.', '');

        return $this;
    }

    public function getContributionAmount(): float
    {
        return (float) $this->contributionName;
    }

    public function getContractUrl(): ?string
    {
        return $this->contractUrl;
    }

    public function setContractUrl(?string $contractUrl): self
    {
        $this->contractUrl = $contractUrl !== null && trim($contractUrl) !== '' ? trim($contractUrl) : null;

        return $this;
    }

    public function getAccessCode(): ?string
    {
        return $this->accessCode;
    }

    public function setAccessCode(?string $accessCode): self
    {
        $this->accessCode = $accessCode !== null && trim($accessCode) !== '' ? trim($accessCode) : null;

        return $this;
    }

    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    public function setIndustry(?string $industry): self
    {
        $this->industry = $industry !== null && trim($industry) !== '' ? trim($industry) : null;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        if ($phone === null || trim($phone) === '') {
            $this->phone = null;

            return $this;
        }

        $normalized = preg_replace('/\\D+/', '', $phone) ?? '';
        $this->phone = $normalized;

        return $this;
    }

    public function getDocumentUrl(): ?string
    {
        return $this->documentUrl;
    }

    public function setDocumentUrl(?string $documentUrl): self
    {
        $this->documentUrl = $documentUrl !== null && trim($documentUrl) !== '' ? trim($documentUrl) : null;

        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): self
    {
        $this->taxId = $taxId !== null && trim($taxId) !== '' ? strtoupper(trim($taxId)) : null;

        return $this;
    }
}
