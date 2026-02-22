<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryUploaderCHAT
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
            'url' => ['secure' => true],
        ]);

        $this->defaultFolder = $defaultFolder ?: null;
    }

//    public function uploadFromPath(string $path, ?string $folder = null): array
//    {
//        $options = [
//            'resource_type' => 'auto',
//            'type' => 'upload',
//            'access_mode' => 'public',
//            'use_filename' => true,
//            'unique_filename' => true,
//        ];
//
//        $finalFolder = $folder ?: $this->defaultFolder;
//        if ($finalFolder) $options['folder'] = $finalFolder;
//
//        $result = $this->cloudinary->uploadApi()->upload($path, $options);
//
//        return [
//            'secure_url' => $result['secure_url'] ?? null,
//            'public_id' => $result['public_id'] ?? null,
//            'resource_type' => $result['resource_type'] ?? null,
//            'format' => $result['format'] ?? null,
//        ];
//    }
//
//    public function publicUrl(string $publicId, string $resourceType = 'raw', ?string $format = null): string
//    {
//        $cloudName = $this->cloudinary->configuration->cloud->cloudName;
//        $ext = $format ? '.'.$format : '';
//        return "https://res.cloudinary.com/{$cloudName}/{$resourceType}/upload/{$publicId}{$ext}";
//    }
//
//    public function uploadRawFromPath(string $path, ?string $folder = null): array
//    {
//        $options = [
//            'resource_type' => 'raw',
//            'type' => 'upload',
//            'access_mode' => 'public',
//            'use_filename' => true,
//            'unique_filename' => true,
//        ];
//
//        $finalFolder = $folder ?: $this->defaultFolder;
//        if ($finalFolder) $options['folder'] = $finalFolder;
//
//        $result = $this->cloudinary->uploadApi()->upload($path, $options);
//
//        return [
//            'secure_url' => $result['secure_url'] ?? null,
//            'public_id' => $result['public_id'] ?? null,
//            'resource_type' => $result['resource_type'] ?? null,
//            'format' => $result['format'] ?? null,
//        ];
//    }

    public function uploadFromPath(string $path, ?string $folder = null): array
    {
        $options = [
            'resource_type' => 'auto',
            'use_filename' => true,
            'unique_filename' => true,
        ];

        $finalFolder = $folder ?: $this->defaultFolder;
        if ($finalFolder) {
            $options['folder'] = $finalFolder;
        }

        $result = $this->cloudinary->uploadApi()->upload($path, $options);

        return [
            'secure_url' => $result['secure_url'] ?? null,
            'public_id' => $result['public_id'] ?? null,
            'bytes' => isset($result['bytes']) ? (int) $result['bytes'] : null,
            'resource_type' => $result['resource_type'] ?? null,
            'format' => $result['format'] ?? null,
        ];
    }

    public function publicUrl(string $publicId, string $resourceType = 'raw', ?string $format = null): string
    {
        $cloudName = $this->cloudinary->configuration->cloud->cloudName;
        $ext = $format ? '.'.$format : '';
        return "https://res.cloudinary.com/{$cloudName}/{$resourceType}/upload/{$publicId}{$ext}";
    }

    /**
     * Upload as RAW and force public delivery (good for PDF/ZIP)
     * @return array{secure_url: string|null, public_id: string|null, resource_type: string|null, format: string|null}
     */
    public function uploadRawFromPath(string $path, ?string $folder = null): array
    {
        $options = [
            'resource_type' => 'raw',
            'type' => 'upload',          // public upload type
            'access_mode' => 'public',   // ✅ force public delivery
            'use_filename' => true,
            'unique_filename' => true,
        ];

        $finalFolder = $folder ?: $this->defaultFolder;
        if ($finalFolder) {
            $options['folder'] = $finalFolder;
        }

        $result = $this->cloudinary->uploadApi()->upload($path, $options);

        return [
            'secure_url' => $result['secure_url'] ?? null,
            'public_id' => $result['public_id'] ?? null,
            'resource_type' => $result['resource_type'] ?? null,
            'format' => $result['format'] ?? null,
        ];
    }
}