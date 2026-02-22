<?php

namespace App\Entity;

use App\Repository\MessageAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageAttachmentRepository::class)]
class MessageAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Message $message = null;
    #[ORM\Column(length: 255)]
    private ?string $original_name = null;

    #[ORM\Column(length: 255)]
    private ?string $storedName = null;

    #[ORM\Column(length: 255)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publicId = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $cloudResourceType = null;

    public function getCloudResourceType(): ?string { return $this->cloudResourceType; }
    public function setCloudResourceType(?string $t): static { $this->cloudResourceType = $t; return $this; }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalName(): ?string
    {
        return $this->original_name;
    }

    public function setOriginalName(string $original_name): static
    {
        $this->original_name = $original_name;

        return $this;
    }

    public function getStoredName(): ?string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): static
    {
        $this->storedName = $storedName;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getMessage(): ?Message { return $this->message; }
    public function setMessage(?Message $message): self { $this->message = $message; return $this; }

    public function getPublicId(): ?string{ return $this->publicId; }
    public function setPublicId(?string $publicId): static{
        $this->publicId = $publicId;
        return $this;
    }

    public function getUrl(): ?string{ return $this->url; }
    public function setUrl(?string $url): static{
        $this->url = $url;
        return $this;
    }
}
