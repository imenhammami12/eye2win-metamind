<?php

namespace App\Entity;

use App\Repository\LiveStreamRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: LiveStreamRepository::class)]
#[ORM\Table(name: 'live_stream')]
class LiveStream
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $coach = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    private int $coinPrice = 0;

    #[ORM\Column(length: 50)]
    private string $status = 'scheduled'; // scheduled, live, ended

#[ORM\Column(type: 'string', length: 191, unique: true)]
    private string $streamKey = '';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endedAt = null;

    #[ORM\OneToMany(mappedBy: 'liveStream', targetEntity: LiveAccess::class, cascade: ['remove'])]
    private Collection $accesses;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->streamKey = bin2hex(random_bytes(16));
        $this->accesses = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCoach(): ?User { return $this->coach; }
    public function setCoach(?User $coach): static { $this->coach = $coach; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCoinPrice(): int { return $this->coinPrice; }
    public function setCoinPrice(int $coinPrice): static { $this->coinPrice = $coinPrice; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getStreamKey(): string { return $this->streamKey; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getStartedAt(): ?\DateTimeInterface { return $this->startedAt; }
    public function setStartedAt(?\DateTimeInterface $startedAt): static { $this->startedAt = $startedAt; return $this; }
    public function getEndedAt(): ?\DateTimeInterface { return $this->endedAt; }
    public function setEndedAt(?\DateTimeInterface $endedAt): static { $this->endedAt = $endedAt; return $this; }
    public function getAccesses(): Collection { return $this->accesses; }

    public function isLive(): bool { return $this->status === 'live'; }
    public function isEnded(): bool { return $this->status === 'ended'; }
}
