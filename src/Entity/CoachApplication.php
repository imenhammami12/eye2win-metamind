<?php

namespace App\Entity;

use App\Repository\CoachApplicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CoachApplicationRepository::class)]
class CoachApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'coachApplications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20, enumType: ApplicationStatus::class)]
    private ?ApplicationStatus $status = ApplicationStatus::PENDING;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les certifications sont obligatoires')]
    #[Assert\Length(min: 20)]
    private ?string $certifications = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'L\'expérience est obligatoire')]
    #[Assert\Length(min: 50)]
    private ?string $experience = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $submittedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reviewComment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documents = null;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cvFile = null;

    public function __construct()
    {
        $this->submittedAt = new \DateTime();
        $this->status = ApplicationStatus::PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStatus(): ?ApplicationStatus
    {
        return $this->status;
    }

    public function setStatus(ApplicationStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCertifications(): ?string
    {
        return $this->certifications;
    }

    public function setCertifications(string $certifications): static
    {
        $this->certifications = $certifications;
        return $this;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(string $experience): static
    {
        $this->experience = $experience;
        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeInterface
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeInterface $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeInterface
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeInterface $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function getReviewComment(): ?string
    {
        return $this->reviewComment;
    }

    public function setReviewComment(?string $reviewComment): static
    {
        $this->reviewComment = $reviewComment;
        return $this;
    }

    public function getDocuments(): ?string
    {
        return $this->documents;
    }

    public function setDocuments(?string $documents): static
    {
        $this->documents = $documents;
        return $this;
    }

    public function approve(string $comment = null): void
    {
        $this->status = ApplicationStatus::APPROVED;
        $this->reviewedAt = new \DateTime();
        $this->reviewComment = $comment;
        
        // Ajouter le rôle COACH à l'utilisateur
        $roles = $this->user->getRoles();
        if (!in_array('ROLE_COACH', $roles)) {
            $roles[] = 'ROLE_COACH';
            $this->user->setRoles($roles);
        }
    }

    public function reject(string $comment): void
    {
        $this->status = ApplicationStatus::REJECTED;
        $this->reviewedAt = new \DateTime();
        $this->reviewComment = $comment;
    }


     public function getCvFile(): ?string
    {
        return $this->cvFile;
    }

    public function setCvFile(?string $cvFile): static
    {
        $this->cvFile = $cvFile;
        return $this;
    }
}