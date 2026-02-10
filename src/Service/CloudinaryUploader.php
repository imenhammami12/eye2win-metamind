<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryUploader
{
    private Cloudinary $cloudinary;
    private ?string $defaultFolder;

    public function __construct(string $cloudName, string $apiKey, string $apiSecret, ?string $defaultFolder = null)
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
        $this->defaultFolder = $defaultFolder ?: null;
    }

    /**
     * @return array{secure_url: string|null, public_id: string|null, duration: float|null}
     */
    public function uploadVideo(UploadedFile $file): array
    {
        $options = [
            'resource_type' => 'video',
        ];

        if ($this->defaultFolder) {
            $options['folder'] = $this->defaultFolder;
        }

        $result = $this->cloudinary->uploadApi()->upload($file->getPathname(), $options);

        return [
            'secure_url' => $result['secure_url'] ?? null,
            'public_id' => $result['public_id'] ?? null,
            'duration' => isset($result['duration']) ? (float) $result['duration'] : null,
        ];
    }
}
