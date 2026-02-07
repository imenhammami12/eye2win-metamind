<?php

namespace App\Entity;

use App\Repository\PlayerStatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerStatRepository::class)]
class PlayerStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column]
    private ?float $accuracy = null;

    #[ORM\Column]
    private ?int $actionsCount = null;

    #[ORM\ManyToOne(inversedBy: 'playerStats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?video $videomatch = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }

    public function setAccuracy(float $accuracy): static
    {
        $this->accuracy = $accuracy;

        return $this;
    }

    public function getActionsCount(): ?int
    {
        return $this->actionsCount;
    }

    public function setActionsCount(int $actionsCount): static
    {
        $this->actionsCount = $actionsCount;

        return $this;
    }

    public function getVideomatch(): ?video
    {
        return $this->videomatch;
    }

    public function setVideomatch(?video $videomatch): static
    {
        $this->videomatch = $videomatch;

        return $this;
    }
}
