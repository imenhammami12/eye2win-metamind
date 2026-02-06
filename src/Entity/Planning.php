<?php

namespace App\Entity;

use App\Repository\PlanningRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanningRepository::class)]
class Planning
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'IDplanning')]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'Date is required')]
    #[Assert\GreaterThanOrEqual("today", message: 'The date cannot be in the past')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'Time is required')]
    private ?\DateTimeInterface $time = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $localisation = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $needPartner = null;

    #[ORM\Column(enumType: PlanningLevel::class)]
    private ?PlanningLevel $level = null;

    #[ORM\Column(enumType: PlanningType::class)]
    private ?PlanningType $type = null;

    #[ORM\OneToMany(mappedBy: 'planning', targetEntity: TrainingSession::class)]
    private Collection $trainingSessions;

    public function __construct()
    {
        $this->trainingSessions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTime(): ?\DateTimeInterface
    {
        return $this->time;
    }

    public function setTime(?\DateTimeInterface $time): static
    {
        $this->time = $time;

        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): static
    {
        $this->localisation = $localisation;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isNeedPartner(): ?bool
    {
        return $this->needPartner;
    }

    public function setNeedPartner(bool $needPartner): static
    {
        $this->needPartner = $needPartner;

        return $this;
    }

    public function getLevel(): ?PlanningLevel
    {
        return $this->level;
    }

    public function setLevel(PlanningLevel $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getType(): ?PlanningType
    {
        return $this->type;
    }

    public function setType(PlanningType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection<int, TrainingSession>
     */
    public function getTrainingSessions(): Collection
    {
        return $this->trainingSessions;
    }

    public function addTrainingSession(TrainingSession $trainingSession): static
    {
        if (!$this->trainingSessions->contains($trainingSession)) {
            $this->trainingSessions->add($trainingSession);
            $trainingSession->setPlanning($this);
        }

        return $this;
    }

    public function removeTrainingSession(TrainingSession $trainingSession): static
    {
        if ($this->trainingSessions->removeElement($trainingSession)) {
            // set the owning side to null (unless already changed)
            if ($trainingSession->getPlanning() === $this) {
                $trainingSession->setPlanning(null);
            }
        }

        return $this;
    }
}
