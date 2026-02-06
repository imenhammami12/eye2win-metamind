<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'This email address is already registered')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'The email "{{ value }}" is not valid')]
    private ?string $email = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Username is required')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Username must be at least {{ limit }} characters long',
        maxMessage: 'Username cannot be longer than {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_]+$/',
        message: 'Username can only contain letters, numbers, and underscores'
    )]
    private ?string $username = null;

    #[ORM\Column(type: 'text')]
    private string $rolesJson = '[]';

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 20, enumType: AccountStatus::class)]
    private ?AccountStatus $accountStatus = AccountStatus::ACTIVE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'Full name is required')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Full name must be at least {{ limit }} characters long',
        maxMessage: 'Full name cannot be longer than {{ limit }} characters'
    )]
    private ?string $fullName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Bio cannot be longer than {{ limit }} characters'
    )]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Team::class, orphanRemoval: true)]
    private Collection $ownedTeams;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TeamMembership::class, orphanRemoval: true)]
    private Collection $teamMemberships;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CoachApplication::class, orphanRemoval: true)]
    private Collection $coachApplications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AuditLog::class)]
    private Collection $auditLogs;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TrainingSession::class)]
    private Collection $trainingSessions;

    public function __construct()
    {
        $this->ownedTeams = new ArrayCollection();
        $this->teamMemberships = new ArrayCollection();
        $this->coachApplications = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->trainingSessions = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->lastLogin = new \DateTime();
        $this->accountStatus = AccountStatus::ACTIVE;
        $this->rolesJson = json_encode(['ROLE_USER']);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = json_decode($this->rolesJson, true) ?: [];
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->rolesJson = json_encode(array_values($roles));
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getAccountStatus(): ?AccountStatus
    {
        return $this->accountStatus;
    }

    public function setAccountStatus(AccountStatus $accountStatus): static
    {
        $this->accountStatus = $accountStatus;
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

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(\DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;
        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getOwnedTeams(): Collection
    {
        return $this->ownedTeams;
    }

    /**
     * @return Collection<int, TeamMembership>
     */
    public function getTeamMemberships(): Collection
    {
        return $this->teamMemberships;
    }

    /**
     * @return Collection<int, CoachApplication>
     */
    public function getCoachApplications(): Collection
    {
        return $this->coachApplications;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function isActive(): bool
    {
        return $this->accountStatus === AccountStatus::ACTIVE;
    }

    /**
     * @return Collection<int, TrainingSession>
     */
    public function getTrainingSessions(): Collection
    {
        return $this->trainingSessions;
    }
}