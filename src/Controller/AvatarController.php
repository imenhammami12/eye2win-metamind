<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/avatar')]
#[IsGranted('ROLE_USER')]
class AvatarController extends AbstractController
{

    // Modèle principal — FLUX.1-dev via hf-inference
    private const API_URL_PRIMARY = 'https://router.huggingface.co/hf-inference/models/black-forest-labs/FLUX.1-dev';

    // Fallback — stable-diffusion-xl si FLUX rate limiting
    private const API_URL_FALLBACK = 'https://router.huggingface.co/hf-inference/models/stabilityai/stable-diffusion-xl-base-1.0';

    // Prompts par style — [DESC] = description de l'utilisateur
    private const STYLE_PROMPTS = [
        'anime'     => 'anime style portrait of [DESC], Studio Ghibli art style, detailed face, vibrant colors, soft shading, beautiful illustration, high quality',
        'cartoon'   => 'cartoon portrait of [DESC], Pixar 3D animation style, expressive face, clean bright colors, professional character design, high quality',
        'manga'     => 'manga portrait of [DESC], black and white ink drawing, detailed linework, shounen manga art style, professional illustration',
        'pixel'     => 'pixel art portrait of [DESC], 16-bit retro game character, colorful, detailed pixel art, RPG game sprite style',
        'fantasy'   => 'fantasy portrait of [DESC], epic digital painting, magical atmosphere, detailed face, professional concept art, vibrant dramatic colors',
        'realistic' => 'hyperrealistic portrait of [DESC], professional photography, sharp details, beautiful studio lighting, high quality, photorealistic',
    ];

    #[Route('/toonify', name: 'avatar_toonify', methods: ['POST'])]
    public function toonify(
        Request                $request,
        EntityManagerInterface $em,
        LoggerInterface        $logger
    ): JsonResponse {

        // ── CSRF ──
        if (!$this->isCsrfTokenValid('avatar_toonify', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        // ── Description (obligatoire) ──
        $description = trim($request->request->get('description', ''));
        if (strlen($description) < 3) {
            return new JsonResponse(['error' => 'Please enter a description (at least 3 characters)'], 400);
        }

        // ── Token HuggingFace ──
        $hfToken = $_ENV['HUGGINGFACE_TOKEN'] ?? '';
        if (empty($hfToken)) {
            $logger->error('[Avatar] HUGGINGFACE_TOKEN missing in .env');
            return new JsonResponse(['error' => 'API token not configured. Add HUGGINGFACE_TOKEN to .env'], 500);
        }

        // ── Build prompt ──
        $style    = $request->request->get('style', 'anime');
        $template = self::STYLE_PROMPTS[$style] ?? self::STYLE_PROMPTS['anime'];
        $prompt   = str_replace('[DESC]', $description, $template);

        $logger->info('[Avatar] Style=' . $style . ' | Prompt=' . $prompt);

        // ── Appel API (primary, puis fallback si 429/5xx) ──
        $imageData = null;
        $lastError = '';

        foreach ([self::API_URL_PRIMARY, self::API_URL_FALLBACK] as $apiUrl) {
            $logger->info('[Avatar] Trying: ' . $apiUrl);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['inputs' => $prompt]),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $hfToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            $logger->info('[Avatar] HTTP=' . $httpCode . ' | size=' . strlen((string)$response) . ' bytes');

            if ($curlErr) {
                $lastError = 'cURL error: ' . $curlErr;
                continue;
            }

            // 503 = modèle cold start
            if ($httpCode === 503) {
                $body = json_decode((string)$response, true);
                $wait = isset($body['estimated_time']) ? (int)ceil($body['estimated_time']) : 20;
                return new JsonResponse([
                    'error'         => 'Model is warming up, please retry in ' . $wait . 's.',
                    'model_loading' => true,
                    'retry_after'   => $wait,
                ], 503);
            }

            // 429 = rate limit → essaie fallback
            if ($httpCode === 429) {
                $lastError = 'Rate limit on ' . $apiUrl;
                $logger->warning('[Avatar] 429 rate limit, trying fallback...');
                continue;
            }

            if ($httpCode === 200 && strlen((string)$response) > 500) {
                $imageData = $response;
                break;
            }

            // Autre erreur
            $body      = json_decode((string)$response, true);
            $lastError = $body['error'] ?? ('HTTP ' . $httpCode . ' from ' . $apiUrl);
            $logger->error('[Avatar] Error: ' . $lastError);
        }

        if ($imageData === null) {
            return new JsonResponse(['error' => $lastError . ' — Please retry in a few seconds.'], 500);
        }

        // ── Vérification image valide ──
        $isPng  = substr($imageData, 0, 4) === "\x89PNG";
        $isJpeg = substr($imageData, 0, 2) === "\xFF\xD8";
        $isWebp = substr($imageData, 8, 4) === 'WEBP';

        if (!$isPng && !$isJpeg && !$isWebp) {
            $body = json_decode((string)$imageData, true);
            $msg  = $body['error'] ?? 'Invalid image data received';
            $logger->error('[Avatar] Not a valid image: ' . $msg . ' | bytes: ' . bin2hex(substr($imageData, 0, 8)));
            return new JsonResponse(['error' => $msg . ' — Please retry.'], 500);
        }

        // ── Sauvegarder ──
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext      = $isPng ? 'png' : ($isWebp ? 'webp' : 'jpg');
        $filename = 'avatar_tmp_' . uniqid() . '.' . $ext;
        file_put_contents($uploadDir . $filename, $imageData);

        $logger->info('[Avatar] ✅ Saved: ' . $filename . ' (' . strlen($imageData) . ' bytes)');

        return new JsonResponse([
            'success'    => true,
            'output_url' => '/uploads/profiles/' . $filename,
        ]);
    }

    #[Route('/save', name: 'avatar_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('avatar_save', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid token'], 403);
        }

        $imageUrl = $request->request->get('image_url');
        if (!$imageUrl || !str_starts_with($imageUrl, '/uploads/profiles/')) {
            return new JsonResponse(['error' => 'Invalid image_url'], 400);
        }

        $user      = $this->getUser();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/';
        $localPath = $this->getParameter('kernel.project_dir') . '/public' . $imageUrl;

        if (!file_exists($localPath)) {
            return new JsonResponse(['error' => 'File not found'], 500);
        }

        $filename  = 'avatar_' . $user->getId() . '_' . time() . '.jpg';
        $imageData = file_get_contents($localPath);

        // Supprimer l'ancien avatar et les tmp
        $old = $user->getProfilePicture();
        if ($old && str_starts_with($old, 'avatar_') && file_exists($uploadDir . $old)) {
            unlink($uploadDir . $old);
        }
        foreach (glob($uploadDir . 'avatar_tmp_*') as $tmp) {
            unlink($tmp);
        }

        file_put_contents($uploadDir . $filename, $imageData);
        $user->setProfilePicture($filename);
        $em->flush();

        return new JsonResponse(['success' => true, 'url' => '/uploads/profiles/' . $filename]);
    }
}