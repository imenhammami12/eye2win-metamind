<?php

namespace App\Entity;

use App\Repository\JoueurValorantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JoueurValorantRepository::class)]
#[ORM\Table(name: 'valorant_joueur')]
class JoueurValorant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $trackerPlayerId = null;

    #[ORM\Column(length: 120)]
    private ?string $riotName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $riotTag = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $agent = null;

    #[ORM\ManyToOne(inversedBy: 'joueurs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MatchValorant $match = null;

    #[ORM\ManyToOne(inversedBy: 'joueurs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EquipeValorant $equipe = null;

    #[ORM\OneToOne(mappedBy: 'joueur', targetEntity: StatistiqueValorant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?StatistiqueValorant $statistique = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrackerPlayerId(): ?string
    {
        return $this->trackerPlayerId;
    }

    public function setTrackerPlayerId(?string $trackerPlayerId): static
    {
        $this->trackerPlayerId = $trackerPlayerId;
        return $this;
    }

    public function getRiotName(): ?string
    {
        return $this->riotName;
    }

    public function setRiotName(string $riotName): static
    {
        $this->riotName = $riotName;
        return $this;
    }

    public function getRiotTag(): ?string
    {
        return $this->riotTag;
    }

    public function setRiotTag(?string $riotTag): static
    {
        $this->riotTag = $riotTag;
        return $this;
    }

    public function getAgent(): ?string
    {
        return $this->agent;
    }

    public function setAgent(?string $agent): static
    {
        $this->agent = $agent;
        return $this;
    }

    public function getMatch(): ?MatchValorant
    {
        return $this->match;
    }

    public function setMatch(?MatchValorant $match): static
    {
        $this->match = $match;
        return $this;
    }

    public function getEquipe(): ?EquipeValorant
    {
        return $this->equipe;
    }

    public function setEquipe(?EquipeValorant $equipe): static
    {
        $this->equipe = $equipe;
        return $this;
    }

    public function getStatistique(): ?StatistiqueValorant
    {
        return $this->statistique;
    }

    public function setStatistique(?StatistiqueValorant $statistique): static
    {
        if ($statistique !== null && $statistique->getJoueur() !== $this) {
            $statistique->setJoueur($this);
        }

        $this->statistique = $statistique;
        return $this;
    }
}
