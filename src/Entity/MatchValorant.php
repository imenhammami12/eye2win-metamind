<?php

namespace App\Entity;

use App\Repository\MatchValorantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchValorantRepository::class)]
#[ORM\Table(name: 'valorant_match')]
#[ORM\UniqueConstraint(name: 'uniq_valorant_match_owner', columns: ['owner_id', 'tracker_match_id'])]
class MatchValorant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $trackerMatchId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mapName = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mode = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $playedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreTeamA = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreTeamB = null;

    #[ORM\Column(length: 20)]
    private string $status = 'IMPORTED';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rawData = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\ManyToOne(inversedBy: 'valorantMatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /** @var Collection<int, EquipeValorant> */
    #[ORM\OneToMany(mappedBy: 'match', targetEntity: EquipeValorant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $equipes;

    /** @var Collection<int, JoueurValorant> */
    #[ORM\OneToMany(mappedBy: 'match', targetEntity: JoueurValorant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $joueurs;

    public function __construct()
    {
        $this->equipes = new ArrayCollection();
        $this->joueurs = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrackerMatchId(): ?string
    {
        return $this->trackerMatchId;
    }

    public function setTrackerMatchId(string $trackerMatchId): static
    {
        $this->trackerMatchId = $trackerMatchId;
        return $this;
    }

    public function getMapName(): ?string
    {
        return $this->mapName;
    }

    public function setMapName(?string $mapName): static
    {
        $this->mapName = $mapName;
        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): static
    {
        $this->mode = $mode;
        return $this;
    }

    public function getPlayedAt(): ?\DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function setPlayedAt(?\DateTimeImmutable $playedAt): static
    {
        $this->playedAt = $playedAt;
        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): static
    {
        $this->durationSeconds = $durationSeconds;
        return $this;
    }

    public function getScoreTeamA(): ?int
    {
        return $this->scoreTeamA;
    }

    public function setScoreTeamA(?int $scoreTeamA): static
    {
        $this->scoreTeamA = $scoreTeamA;
        return $this;
    }

    public function getScoreTeamB(): ?int
    {
        return $this->scoreTeamB;
    }

    public function setScoreTeamB(?int $scoreTeamB): static
    {
        $this->scoreTeamB = $scoreTeamB;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    public function setRawData(?array $rawData): static
    {
        $this->rawData = $rawData;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): static
    {
        $this->archivedAt = $archivedAt;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    /** @return Collection<int, EquipeValorant> */
    public function getEquipes(): Collection
    {
        return $this->equipes;
    }

    public function addEquipe(EquipeValorant $equipe): static
    {
        if (!$this->equipes->contains($equipe)) {
            $this->equipes->add($equipe);
            $equipe->setMatch($this);
        }
        return $this;
    }

    public function removeEquipe(EquipeValorant $equipe): static
    {
        if ($this->equipes->removeElement($equipe) && $equipe->getMatch() === $this) {
            $equipe->setMatch(null);
        }
        return $this;
    }

    /** @return Collection<int, JoueurValorant> */
    public function getJoueurs(): Collection
    {
        return $this->joueurs;
    }

    public function addJoueur(JoueurValorant $joueur): static
    {
        if (!$this->joueurs->contains($joueur)) {
            $this->joueurs->add($joueur);
            $joueur->setMatch($this);
        }
        return $this;
    }

    public function removeJoueur(JoueurValorant $joueur): static
    {
        if ($this->joueurs->removeElement($joueur) && $joueur->getMatch() === $this) {
            $joueur->setMatch(null);
        }
        return $this;
    }
}
