<?php

namespace App\Entity;

use App\Repository\GuideVideoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GuideVideoRepository::class)]
#[ORM\Table(name: 'guide_video')]
class GuideVideo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Title is required')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Title must be at least 3 characters')]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Description cannot exceed 1000 characters')]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Video URL is required')]
    #[Assert\Url(message: 'Video URL must be valid')]
    private ?string $videoUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Map is required')]
    private ?string $map = 'All';

    #[ORM\Column]
    private int $likes = 0;

    #[ORM\Column]
    private int $views = 0;

    #[ORM\Column(length: 20)]
    private string $status = 'pending'; // pending, approved, rejected

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $approvedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $uploadedBy = null;

    #[ORM\ManyToOne(inversedBy: 'guideVideos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne(inversedBy: 'guideVideos')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Agent $agent = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'guide_video_likes')]
    private Collection $likedBy;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->likes = 0;
        $this->views = 0;
        $this->status = 'pending';
        $this->map = 'All';
        $this->likedBy = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(string $videoUrl): static
    {
        $this->videoUrl = $videoUrl;
        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function getMap(): ?string
    {
        return $this->map;
    }

    public function setMap(string $map): static
    {
        $this->map = $map;
        return $this;
    }

    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }

    public function addLike(): static
    {
        $this->likes++;
        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;
        return $this;
    }

    public function addView(): static
    {
        $this->views++;
        return $this;
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

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getApprovedAt(): ?\DateTime
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTime $approvedAt): static
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;
        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;
        return $this;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): static
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getLikedBy(): Collection
    {
        return $this->likedBy;
    }

    public function addLikedBy(User $user): static
    {
        if (!$this->likedBy->contains($user)) {
            $this->likedBy->add($user);
            $this->likes++;
        }

        return $this;
    }

    public function removeLikedBy(User $user): static
    {
        if ($this->likedBy->removeElement($user)) {
            $this->likes = max(0, $this->likes - 1);
        }

        return $this;
    }

    public function isLikedByUser(User $user): bool
    {
        return $this->likedBy->contains($user);
    }
}
