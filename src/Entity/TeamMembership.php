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
    private ?Team $team = null;

    #[ORM\ManyToOne(inversedBy: 'teamMemberships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(enumType: MemberRole::class)]
    private ?MemberRole $role = null;

    #[ORM\Column(enumType: MembershipStatus::class)]
    private ?MembershipStatus $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $invitedAt = null;

    /**
     * IMPORTANT: joinedAt is nullable because:
     * - PENDING requests don't have a joinedAt yet
     * - INVITED users haven't joined yet
     * - Only ACTIVE members have joinedAt set
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $joinedAt = null;

    public function __construct()
    {
        $this->invitedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
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

    public function getInvitedAt(): ?\DateTimeInterface
    {
        return $this->invitedAt;
    }

    public function setInvitedAt(?\DateTimeInterface $invitedAt): static
    {
        $this->invitedAt = $invitedAt;
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

    /**
     * Accept invitation or join request
     * Sets status to ACTIVE and sets joinedAt to now
     */
    public function accept(): void
    {
        $this->status = MembershipStatus::ACTIVE;
        // Set joinedAt when accepting (if not already set)
        if ($this->joinedAt === null) {
            $this->joinedAt = new \DateTime();
        }
    }

    /**
     * Decline invitation
     * Sets status to INACTIVE
     */
    public function decline(): void
    {
        $this->status = MembershipStatus::INACTIVE;
    }

    /**
     * Check if membership is active
     */
    public function isActive(): bool
    {
        return $this->status === MembershipStatus::ACTIVE;
    }

    /**
     * Check if membership is pending
     */
    public function isPending(): bool
    {
        return $this->status === MembershipStatus::PENDING;
    }

    /**
     * Check if user was invited
     */
    public function isInvited(): bool
    {
        return $this->status === MembershipStatus::INVITED;
    }
}