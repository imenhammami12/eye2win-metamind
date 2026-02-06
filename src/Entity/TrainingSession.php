<?php

namespace App\Entity;

use App\Repository\TrainingSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingSessionRepository::class)]
class TrainingSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idtraining')]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'en attente';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\ManyToOne(inversedBy: 'trainingSessions')]
    #[ORM\JoinColumn(name: 'ID_planning', referencedColumnName: 'IDplanning', nullable: false)]
    private ?Planning $planning = null;

    #[ORM\ManyToOne(inversedBy: 'trainingSessions')]
    #[ORM\JoinColumn(name: 'IDcurrent_user', nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getJoinedAt(): ?\DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeInterface $joinedAt): static
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }

    public function getPlanning(): ?Planning
    {
        return $this->planning;
    }

    public function setPlanning(?Planning $planning): static
    {
        $this->planning = $planning;

        return $this;
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
}
