<?php

namespace App\Entity;

use App\Repository\EquipeValorantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EquipeValorantRepository::class)]
#[ORM\Table(name: 'valorant_equipe')]
class EquipeValorant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $side = null;

    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\ManyToOne(inversedBy: 'equipes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MatchValorant $match = null;

    /** @var Collection<int, JoueurValorant> */
    #[ORM\OneToMany(mappedBy: 'equipe', targetEntity: JoueurValorant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $joueurs;

    public function __construct()
    {
        $this->joueurs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSide(): ?string
    {
        return $this->side;
    }

    public function setSide(?string $side): static
    {
        $this->side = $side;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;
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

    /** @return Collection<int, JoueurValorant> */
    public function getJoueurs(): Collection
    {
        return $this->joueurs;
    }

    public function addJoueur(JoueurValorant $joueur): static
    {
        if (!$this->joueurs->contains($joueur)) {
            $this->joueurs->add($joueur);
            $joueur->setEquipe($this);
        }
        return $this;
    }

    public function removeJoueur(JoueurValorant $joueur): static
    {
        if ($this->joueurs->removeElement($joueur) && $joueur->getEquipe() === $this) {
            $joueur->setEquipe(null);
        }
        return $this;
    }
}
