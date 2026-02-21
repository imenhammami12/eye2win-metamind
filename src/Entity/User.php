<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'This email address is already registered')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
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

    // ===== 💰 EYETWIN COINS =====

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $coinBalance = 0;

    // ===== 2FA FIELDS =====

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isTotpEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $backupCodesJson = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $totpEnabledAt = null;

    // ===== PHONE / TELEGRAM =====

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $telegramChatId = null;

    // ===== FACE RECOGNITION =====

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $faceDescriptor = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $faceImage = null;

    // ===== RELATIONS =====

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

    #[ORM\OneToMany(targetEntity: Video::class, mappedBy: 'uploadedBy', orphanRemoval: true)]
    private Collection $videos;

    public function __construct()
    {
        $this->ownedTeams       = new ArrayCollection();
        $this->teamMemberships  = new ArrayCollection();
        $this->coachApplications = new ArrayCollection();
        $this->notifications    = new ArrayCollection();
        $this->auditLogs        = new ArrayCollection();
        $this->trainingSessions = new ArrayCollection();
        $this->videos           = new ArrayCollection();
        $this->createdAt        = new \DateTime();
        $this->lastLogin        = new \DateTime();
        $this->accountStatus    = AccountStatus::ACTIVE;
        $this->rolesJson        = json_encode(['ROLE_USER']);
        $this->isTotpEnabled    = false;
        $this->coinBalance      = 0;
    }

    // ===== BASIC GETTERS / SETTERS =====

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
        $roles   = json_decode($this->rolesJson, true) ?: [];
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

    public function eraseCredentials(): void {}

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

    // ===== 💰 COINS =====

    public function getCoinBalance(): int
    {
        return $this->coinBalance;
    }

    public function setCoinBalance(int $coinBalance): static
    {
        $this->coinBalance = max(0, $coinBalance); // never go below 0
        return $this;
    }

    // ===== PHONE / TELEGRAM =====

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getTelegramChatId(): ?string
    {
        return $this->telegramChatId;
    }

    public function setTelegramChatId(?string $telegramChatId): static
    {
        $this->telegramChatId = $telegramChatId;
        return $this;
    }

    // ===== FACE RECOGNITION =====

    public function getFaceDescriptor(): ?string
    {
        return $this->faceDescriptor;
    }

    public function setFaceDescriptor(?string $faceDescriptor): static
    {
        $this->faceDescriptor = $faceDescriptor;
        return $this;
    }

    public function getFaceImage(): ?string
    {
        return $this->faceImage;
    }

    public function setFaceImage(?string $faceImage): static
    {
        $this->faceImage = $faceImage;
        return $this;
    }

    // ===== RELATIONS =====

    public function getOwnedTeams(): Collection
    {
        return $this->ownedTeams;
    }

    public function getTeamMemberships(): Collection
    {
        return $this->teamMemberships;
    }

    public function getCoachApplications(): Collection
    {
        return $this->coachApplications;
    }

    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function isActive(): bool
    {
        return $this->accountStatus === AccountStatus::ACTIVE;
    }

    public function getTrainingSessions(): Collection
    {
        return $this->trainingSessions;
    }

    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setUploadedBy($this);
        }
        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getUploadedBy() === $this) {
                $video->setUploadedBy(null);
            }
        }
        return $this;
    }

    // ===== 2FA =====

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->isTotpEnabled && $this->totpSecret !== null;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (!$this->totpSecret) {
            return null;
        }
        return new TotpConfiguration(
            $this->totpSecret,
            TotpConfiguration::ALGORITHM_SHA1,
            30,
            6
        );
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;
        return $this;
    }

    public function getIsTotpEnabled(): bool
    {
        return $this->isTotpEnabled;
    }

    public function setIsTotpEnabled(bool $isTotpEnabled): static
    {
        $this->isTotpEnabled = $isTotpEnabled;
        if ($isTotpEnabled && $this->totpEnabledAt === null) {
            $this->totpEnabledAt = new \DateTimeImmutable();
        } elseif (!$isTotpEnabled) {
            $this->totpEnabledAt = null;
        }
        return $this;
    }

    public function getBackupCodes(): ?array
    {
        if ($this->backupCodesJson === null || $this->backupCodesJson === '') {
            return null;
        }
        $decoded = json_decode($this->backupCodesJson, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function setBackupCodes(?array $backupCodes): static
    {
        $this->backupCodesJson = $backupCodes !== null ? json_encode($backupCodes) : null;
        return $this;
    }

    public function getTotpEnabledAt(): ?\DateTimeImmutable
    {
        return $this->totpEnabledAt;
    }

    public function setTotpEnabledAt(?\DateTimeImmutable $totpEnabledAt): static
    {
        $this->totpEnabledAt = $totpEnabledAt;
        return $this;
    }

    public function invalidateBackupCode(string $code): bool
    {
        $codes = $this->getBackupCodes();
        if ($codes === null) {
            return false;
        }
        $key = array_search($code, $codes, true);
        if ($key !== false) {
            unset($codes[$key]);
            $this->setBackupCodes(array_values($codes));
            return true;
        }
        return false;
    }

    public function getRemainingBackupCodesCount(): int
    {
        $codes = $this->getBackupCodes();
        return $codes ? count($codes) : 0;
    }
}