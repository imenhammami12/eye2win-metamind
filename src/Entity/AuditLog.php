<?php

namespace App\Entity;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_created_at')]
#[ORM\Index(columns: ['entity_type'], name: 'idx_audit_entity_type')]
class AuditLog
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column]
private ?int $id = null;
#[ORM\ManyToOne(inversedBy: 'auditLogs')]
private ?User $user = null;

#[ORM\Column(length: 50)]
private ?string $action = null;

#[ORM\Column(length: 100)]
private ?string $entityType = null;

#[ORM\Column(nullable: true)]
private ?int $entityId = null;

#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $details = null;

#[ORM\Column(length: 45, nullable: true)]
private ?string $ipAddress = null;

#[ORM\Column(type: Types::DATETIME_MUTABLE)]
private ?\DateTimeInterface $createdAt = null;

public function __construct()
{
    $this->createdAt = new \DateTime();
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

public function getAction(): ?string
{
    return $this->action;
}

public function setAction(string $action): static
{
    $this->action = $action;
    return $this;
}

public function getEntityType(): ?string
{
    return $this->entityType;
}

public function setEntityType(string $entityType): static
{
    $this->entityType = $entityType;
    return $this;
}

public function getEntityId(): ?int
{
    return $this->entityId;
}

public function setEntityId(?int $entityId): static
{
    $this->entityId = $entityId;
    return $this;
}

public function getDetails(): ?string
{
    return $this->details;
}

public function setDetails(?string $details): static
{
    $this->details = $details;
    return $this;
}

public function getIpAddress(): ?string
{
    return $this->ipAddress;
}

public function setIpAddress(?string $ipAddress): static
{
    $this->ipAddress = $ipAddress;
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
}