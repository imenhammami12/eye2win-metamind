<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'game')]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Game name is required')]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Game slug is required')]
    #[Assert\Regex(pattern: '/^[a-z0-9\-]+$/', message: 'Slug can only contain lowercase letters, numbers, and dashes')]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 7)]
    private ?string $color = '#0a8cc9';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    /**
     * @var Collection<int, Agent>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Agent::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $agents;

    /**
     * @var Collection<int, GuideVideo>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: GuideVideo::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $guideVideos;

    public function __construct()
    {
        $this->agents = new ArrayCollection();
        $this->guideVideos = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, Agent>
     */
    public function getAgents(): Collection
    {
        return $this->agents;
    }

    public function addAgent(Agent $agent): static
    {
        if (!$this->agents->contains($agent)) {
            $this->agents->add($agent);
            $agent->setGame($this);
        }
        return $this;
    }

    public function removeAgent(Agent $agent): static
    {
        if ($this->agents->removeElement($agent)) {
            if ($agent->getGame() === $this) {
                $agent->setGame(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, GuideVideo>
     */
    public function getGuideVideos(): Collection
    {
        return $this->guideVideos;
    }

    public function addGuideVideo(GuideVideo $guideVideo): static
    {
        if (!$this->guideVideos->contains($guideVideo)) {
            $this->guideVideos->add($guideVideo);
            $guideVideo->setGame($this);
        }
        return $this;
    }

    public function removeGuideVideo(GuideVideo $guideVideo): static
    {
        if ($this->guideVideos->removeElement($guideVideo)) {
            if ($guideVideo->getGame() === $this) {
                $guideVideo->setGame(null);
            }
        }
        return $this;
    }
}
