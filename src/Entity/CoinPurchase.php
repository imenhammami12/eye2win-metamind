<?php

namespace App\Entity;

use App\Repository\CoinPurchaseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoinPurchaseRepository::class)]
#[ORM\Table(name: 'coin_purchase')]
class CoinPurchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'integer')]
    private int $coinsAmount = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $pricePaid = '0.00';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(length: 50)]
    private string $status = 'pending'; // pending, completed, failed

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getCoinsAmount(): int { return $this->coinsAmount; }
    public function setCoinsAmount(int $coinsAmount): static { $this->coinsAmount = $coinsAmount; return $this; }
    public function getPricePaid(): string { return $this->pricePaid; }
    public function setPricePaid(string $pricePaid): static { $this->pricePaid = $pricePaid; return $this; }
    public function getStripeSessionId(): ?string { return $this->stripeSessionId; }
    public function setStripeSessionId(?string $id): static { $this->stripeSessionId = $id; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $dt): static { $this->completedAt = $dt; return $this; }
}
