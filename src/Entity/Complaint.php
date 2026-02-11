<?php

namespace App\Entity;

use App\Repository\ComplaintRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ComplaintRepository::class)]
#[ORM\Table(name: 'complaint')]
class Complaint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Subject is required')]
    #[Assert\Length(
        min: 5,
        max: 200,
        minMessage: 'Subject must be at least {{ limit }} characters long',
        maxMessage: 'Subject cannot be longer than {{ limit }} characters'
    )]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Description is required')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Description must be at least {{ limit }} characters long'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 50, enumType: ComplaintCategory::class)]
    #[Assert\NotNull(message: 'Category is required')]
    private ?ComplaintCategory $category = null;

    #[ORM\Column(length: 20, enumType: ComplaintStatus::class)]
    private ?ComplaintStatus $status = ComplaintStatus::PENDING;

    #[ORM\Column(length: 20, enumType: ComplaintPriority::class)]
    private ?ComplaintPriority $priority = ComplaintPriority::MEDIUM;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $submittedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $assignedTo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminResponse = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resolutionNotes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentPath = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = ComplaintStatus::PENDING;
        $this->priority = ComplaintPriority::MEDIUM;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
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

    public function getCategory(): ?ComplaintCategory
    {
        return $this->category;
    }

    public function setCategory(ComplaintCategory $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getStatus(): ?ComplaintStatus
    {
        return $this->status;
    }

    public function setStatus(ComplaintStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();
        
        // Set resolved date when status changes to RESOLVED or CLOSED
        if (in_array($status, [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED]) && $this->resolvedAt === null) {
            $this->resolvedAt = new \DateTime();
        }
        
        return $this;
    }

    public function getPriority(): ?ComplaintPriority
    {
        return $this->priority;
    }

    public function setPriority(ComplaintPriority $priority): static
    {
        $this->priority = $priority;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $submittedBy): static
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?User $assignedTo): static
    {
        $this->assignedTo = $assignedTo;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getResolvedAt(): ?\DateTimeInterface
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeInterface $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;
        return $this;
    }

    public function getAdminResponse(): ?string
    {
        return $this->adminResponse;
    }

    public function setAdminResponse(?string $adminResponse): static
    {
        $this->adminResponse = $adminResponse;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getResolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function setResolutionNotes(?string $resolutionNotes): static
    {
        $this->resolutionNotes = $resolutionNotes;
        return $this;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $attachmentPath): static
    {
        $this->attachmentPath = $attachmentPath;
        return $this;
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED]);
    }

    public function isPending(): bool
    {
        return $this->status === ComplaintStatus::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === ComplaintStatus::IN_PROGRESS;
    }
}
