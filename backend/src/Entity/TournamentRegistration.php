<?php

namespace App\Entity;

use App\Repository\TournamentRegistrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentRegistrationRepository::class)]
#[ORM\Table(name: 'tournament_registration')]
class TournamentRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tournament $tournament = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $lolRank = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $opggUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $leagueofgraphsUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dpmUrl = null;

    #[ORM\Column]
    private bool $isCaptainWilling = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $registeredAt = null;

    public function __construct()
    {
        $this->registeredAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int { return $this->id; }

    public function getTournament(): ?Tournament { return $this->tournament; }
    public function setTournament(?Tournament $tournament): static { $this->tournament = $tournament; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getLolRank(): ?string { return $this->lolRank; }
    public function setLolRank(?string $lolRank): static { $this->lolRank = $lolRank; return $this; }

    public function getOpggUrl(): ?string { return $this->opggUrl; }
    public function setOpggUrl(?string $opggUrl): static { $this->opggUrl = $opggUrl; return $this; }

    public function getLeagueofgraphsUrl(): ?string { return $this->leagueofgraphsUrl; }
    public function setLeagueofgraphsUrl(?string $leagueofgraphsUrl): static { $this->leagueofgraphsUrl = $leagueofgraphsUrl; return $this; }

    public function getDpmUrl(): ?string { return $this->dpmUrl; }
    public function setDpmUrl(?string $dpmUrl): static { $this->dpmUrl = $dpmUrl; return $this; }

    public function isCaptainWilling(): bool { return $this->isCaptainWilling; }
    public function setIsCaptainWilling(bool $isCaptainWilling): static { $this->isCaptainWilling = $isCaptainWilling; return $this; }

    public function getRegisteredAt(): ?\DateTimeImmutable { return $this->registeredAt; }
}
