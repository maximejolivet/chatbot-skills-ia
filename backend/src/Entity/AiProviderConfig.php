<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\TestAiProviderConfigController;
use App\Enum\AiProvider;
use App\Enum\AiProviderTestStatus;
use App\Enum\AiProviderUsage;
use App\Repository\AiProviderConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity(repositoryClass: AiProviderConfigRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
        new Post(
            uriTemplate: '/ai_provider_configs/{id}/test',
            controller: TestAiProviderConfigController::class,
            read: true,
            deserialize: false,
            name: 'test_ai_provider_config',
        ),
    ],
)]
class AiProviderConfig implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200, unique: true)]
    private string $name;

    #[ORM\Column(length: 20, enumType: AiProviderUsage::class)]
    private AiProviderUsage $usage;

    #[ORM\Column(length: 20, enumType: AiProvider::class)]
    private AiProvider $provider;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiEndpoint = null;

    /**
     * Never exposed via the API in read operations (GetCollection/Get) -- write-only,
     * since this backend has no auth and the API is publicly readable.
     */
    #[ApiProperty(readable: false)]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $apiKey = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $baseUrl = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column(length: 10, enumType: AiProviderTestStatus::class)]
    private AiProviderTestStatus $lastTestStatus = AiProviderTestStatus::Unknown;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastTestedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUsage(): AiProviderUsage
    {
        return $this->usage;
    }

    public function setUsage(AiProviderUsage $usage): static
    {
        $this->usage = $usage;

        return $this;
    }

    public function getProvider(): AiProvider
    {
        return $this->provider;
    }

    public function setProvider(AiProvider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getApiEndpoint(): ?string
    {
        return $this->apiEndpoint;
    }

    public function setApiEndpoint(?string $apiEndpoint): static
    {
        $this->apiEndpoint = $apiEndpoint;

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getLastTestStatus(): AiProviderTestStatus
    {
        return $this->lastTestStatus;
    }

    public function setLastTestStatus(AiProviderTestStatus $lastTestStatus): static
    {
        $this->lastTestStatus = $lastTestStatus;

        return $this;
    }

    public function getLastTestedAt(): ?\DateTimeImmutable
    {
        return $this->lastTestedAt;
    }

    public function setLastTestedAt(?\DateTimeImmutable $lastTestedAt): static
    {
        $this->lastTestedAt = $lastTestedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
