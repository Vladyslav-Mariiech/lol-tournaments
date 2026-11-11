<?php

namespace App\Entity;

use App\Repository\UserLolAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserLolAccountRepository::class)]
#[ORM\Table(name: 'user_lol_accounts')]
#[ORM\HasLifecycleCallbacks]
class UserLolAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lolAccounts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $puuid = null;

    #[ORM\Column(length: 100)]
    private ?string $gameName = null;

    #[ORM\Column(length: 50)]
    private ?string $tagLine = null;

    #[ORM\Column(length: 20)]
    private ?string $region = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lolRank = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPrimary = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isUnlinked = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $unlinkedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPuuid(): ?string
    {
        return $this->puuid;
    }

    public function setPuuid(string $puuid): static
    {
        $this->puuid = $puuid;
        return $this;
    }

    public function getGameName(): ?string
    {
        return $this->gameName;
    }

    public function setGameName(string $gameName): static
    {
        $this->gameName = $gameName;
        return $this;
    }

    public function getTagLine(): ?string
    {
        return $this->tagLine;
    }

    public function setTagLine(string $tagLine): static
    {
        $this->tagLine = $tagLine;
        return $this;
    }

    public function getRiotId(): string
    {
        return $this->gameName . '#' . $this->tagLine;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): static
    {
        $this->region = $region;
        return $this;
    }

    public function getLolRank(): ?string
    {
        return $this->lolRank;
    }

    public function setLolRank(?string $lolRank): static
    {
        $this->lolRank = $lolRank;
        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    public function isUnlinked(): bool
    {
        return $this->isUnlinked;
    }

    public function setIsUnlinked(bool $isUnlinked): static
    {
        $this->isUnlinked = $isUnlinked;
        if ($isUnlinked && null === $this->unlinkedAt) {
            $this->unlinkedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getUnlinkedAt(): ?\DateTimeImmutable
    {
        return $this->unlinkedAt;
    }

    public function setUnlinkedAt(?\DateTimeImmutable $unlinkedAt): static
    {
        $this->unlinkedAt = $unlinkedAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
