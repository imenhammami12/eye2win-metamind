<?php

namespace App\Entity;

use App\Repository\LiveAccessRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LiveAccessRepository::class)]
#[ORM\Table(name: 'live_access')]
#[ORM\UniqueConstraint(name: 'unique_user_live', columns: ['user_id', 'live_stream_id'])]
class LiveAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: LiveStream::class, inversedBy: 'accesses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LiveStream $liveStream = null;

    #[ORM\Column(type: 'integer')]
    private int $coinsSpent = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $purchasedAt;

    public function __construct()
    {
        $this->purchasedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getLiveStream(): ?LiveStream { return $this->liveStream; }
    public function setLiveStream(?LiveStream $liveStream): static { $this->liveStream = $liveStream; return $this; }
    public function getCoinsSpent(): int { return $this->coinsSpent; }
    public function setCoinsSpent(int $coinsSpent): static { $this->coinsSpent = $coinsSpent; return $this; }
    public function getPurchasedAt(): \DateTimeInterface { return $this->purchasedAt; }
}
