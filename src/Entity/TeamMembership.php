<?php

namespace App\Entity;

use App\Repository\TeamMembershipRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamMembershipRepository::class)]
class TeamMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'teamMemberships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'teamMemberships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\Column(length: 20, enumType: MemberRole::class)]
    private ?MemberRole $role = MemberRole::MEMBER;

    #[ORM\Column(length: 20, enumType: MembershipStatus::class)]
    private ?MembershipStatus $status = MembershipStatus::INVITED;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $invitedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $respondedAt = null;

    public function __construct()
    {
        $this->invitedAt = new \DateTime();
        $this->status = MembershipStatus::INVITED;
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        return $this;
    }

    public function getRole(): ?MemberRole
    {
        return $this->role;
    }

    public function setRole(MemberRole $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getStatus(): ?MembershipStatus
    {
        return $this->status;
    }

    public function setStatus(MembershipStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getJoinedAt(): ?\DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(?\DateTimeInterface $joinedAt): static
    {
        $this->joinedAt = $joinedAt;
        return $this;
    }

    public function getInvitedAt(): ?\DateTimeInterface
    {
        return $this->invitedAt;
    }

    public function setInvitedAt(?\DateTimeInterface $invitedAt): static
    {
        $this->invitedAt = $invitedAt;
        return $this;
    }

    public function getRespondedAt(): ?\DateTimeInterface
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeInterface $respondedAt): static
    {
        $this->respondedAt = $respondedAt;
        return $this;
    }

    public function accept(): void
    {
        $this->status = MembershipStatus::ACTIVE;
        $this->joinedAt = new \DateTime();
        $this->respondedAt = new \DateTime();
    }

    public function decline(): void
    {
        $this->status = MembershipStatus::INACTIVE;
        $this->respondedAt = new \DateTime();
    }
}