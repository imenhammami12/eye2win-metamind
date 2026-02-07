<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Video;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VideoMatchService
{
    public function __construct(
        private readonly CloudinaryUploader $cloudinaryUploader,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function createFromUpload(User $user, string $title, string $gameType, UploadedFile $file): Video
    {
        $upload = $this->cloudinaryUploader->uploadVideo($file);

        if (empty($upload['secure_url'])) {
            throw new \RuntimeException('Cloudinary upload failed.');
        }

        $video = new Video();
        $video->setTitle($title)
            ->setGameType($gameType)
            ->setVideoUrl($upload['secure_url'])
            ->setPublicId($upload['public_id'] ?? null)
            ->setDuration($upload['duration'] ?? null)
            ->setStatus('UPLOADED')
            ->setUploadedAt(new \DateTime())
            ->setUploadedBy($user);

        $this->entityManager->persist($video);
        $this->entityManager->flush();

        return $video;
    }

    public function updateVideo(Video $video): Video
    {
        $this->entityManager->flush();

        return $video;
    }

    public function deleteVideo(Video $video): void
    {
        $this->entityManager->remove($video);
        $this->entityManager->flush();
    }
}
