<?php

namespace App\Entity;

use App\Repository\AgentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgentRepository::class)]
#[ORM\Table(name: 'agent')]
class Agent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Agent name is required')]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Agent slug is required')]
    #[Assert\Regex(pattern: '/^[a-z0-9\-]+$/', message: 'Slug can only contain lowercase letters, numbers, and dashes')]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'agents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    /**
     * @var Collection<int, GuideVideo>
     */
    #[ORM\OneToMany(mappedBy: 'agent', targetEntity: GuideVideo::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $guideVideos;

    public function __construct()
    {
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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
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

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;
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
            $guideVideo->setAgent($this);
        }
        return $this;
    }

    public function removeGuideVideo(GuideVideo $guideVideo): static
    {
        if ($this->guideVideos->removeElement($guideVideo)) {
            if ($guideVideo->getAgent() === $this) {
                $guideVideo->setAgent(null);
            }
        }
        return $this;
    }
}
