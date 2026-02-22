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

    // Vision LLM — Llama 3.2 Vision pour analyser la photo avec précision
    private const VISION_URL = 'https://router.huggingface.co/hf-inference/models/meta-llama/Llama-3.2-11B-Vision-Instruct/v1/chat/completions';

    // Fallback vision — BLIP base (plus léger)
    private const BLIP_URL = 'https://router.huggingface.co/hf-inference/models/Salesforce/blip-image-captioning-large';

    // Prompts par style — [DESC] = description de l'utilisateur ou extraite de la photo
    private const STYLE_PROMPTS = [
        'anime'     => 'anime style portrait of [DESC], Studio Ghibli art style, detailed face, vibrant colors, soft shading, beautiful illustration, high quality',
        'cartoon'   => 'cartoon portrait of [DESC], Pixar 3D animation style, expressive face, clean bright colors, professional character design, high quality',
        'manga'     => 'manga portrait of [DESC], black and white ink drawing, detailed linework, shounen manga art style, professional illustration',
        'pixel'     => 'pixel art portrait of [DESC], 16-bit retro game character, colorful, detailed pixel art, RPG game sprite style',
        'fantasy'   => 'fantasy portrait of [DESC], epic digital painting, magical atmosphere, detailed face, professional concept art, vibrant dramatic colors',
        'realistic' => 'hyperrealistic portrait of [DESC], professional photography, sharp details, beautiful studio lighting, high quality, photorealistic',
    ];

    // =========================================================================
    //  ROUTE : /avatar/describe
    //  Analyse la photo avec Llama-3.2-Vision (précis) ou BLIP (fallback)
    //  Retourne une description détaillée : cheveux, yeux, peau, traits, etc.
    // =========================================================================
    #[Route('/describe', name: 'avatar_describe', methods: ['POST'])]
    public function describe(Request $request, LoggerInterface $logger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('avatar_describe', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $file = $request->files->get('photo');
        if (!$file) {
            return new JsonResponse(['error' => 'No photo uploaded'], 400);
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            return new JsonResponse(['error' => 'Invalid file type. Use JPG, PNG or WEBP.'], 400);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['error' => 'File too large. Max 5MB.'], 400);
        }

        $hfToken   = $_ENV['HUGGINGFACE_TOKEN'] ?? '';
        $imageData = file_get_contents($file->getPathname());
        $base64    = base64_encode($imageData);
        $dataUri   = 'data:' . $mime . ';base64,' . $base64;

        $logger->info('[Avatar/Describe] Trying Llama-3.2-Vision...');

        // ── Tentative 1 : Llama 3.2 Vision (description précise) ──
        $llamaPayload = json_encode([
            'model'      => 'meta-llama/Llama-3.2-11B-Vision-Instruct',
            'max_tokens' => 120,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => ['url' => $dataUri],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Describe the person in this photo in ONE short sentence for an avatar prompt. Focus on: gender, age, hair color and style, eye color, skin tone, distinctive features (glasses, beard, etc.), expression. Example format: "young woman with long black hair, brown eyes, light skin, wearing glasses, smiling". Reply with ONLY the description, no extra text.',
                        ],
                    ],
                ],
            ],
        ]);

        $ch = curl_init(self::VISION_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $llamaPayload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $hfToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $logger->info('[Avatar/Describe] Llama HTTP=' . $httpCode . ' | response=' . substr((string)$response, 0, 300));

        if (!$curlErr && $httpCode === 200) {
            $data    = json_decode((string)$response, true);
            $caption = $data['choices'][0]['message']['content'] ?? '';
            $caption = trim(strip_tags($caption));

            // Nettoyer les guillemets ou préfixes éventuels
            $caption = trim($caption, '"\'');
            $caption = preg_replace('/^(description:|here is|the person is|i see)/i', '', $caption);
            $caption = trim($caption);

            if (strlen($caption) > 10) {
                $logger->info('[Avatar/Describe] Llama caption: ' . $caption);
                return new JsonResponse(['success' => true, 'description' => $caption, 'source' => 'llama-vision']);
            }
        }

        // ── Tentative 2 : 503 Llama → modèle en chargement ──
        if ($httpCode === 503) {
            $body = json_decode((string)$response, true);
            $wait = isset($body['estimated_time']) ? (int)ceil($body['estimated_time']) : 20;
            return new JsonResponse(['error' => 'Vision model loading', 'model_loading' => true, 'retry_after' => $wait], 503);
        }

        // ── Tentative 3 : Fallback BLIP (moins précis mais fonctionnel) ──
        $logger->info('[Avatar/Describe] Llama failed (HTTP ' . $httpCode . '), trying BLIP fallback...');

        $ch2 = curl_init(self::BLIP_URL);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $imageData,
            CURLOPT_HTTPHEADER     => array_values(array_filter([
                !empty($hfToken) ? 'Authorization: Bearer ' . $hfToken : null,
                'Content-Type: ' . $mime,
            ])),
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        $logger->info('[Avatar/Describe] BLIP HTTP=' . $httpCode2);

        if ($httpCode2 === 200) {
            $data2   = json_decode((string)$response2, true);
            $caption = $data2[0]['generated_text'] ?? $data2['generated_text'] ?? '';
            if (strlen(trim($caption)) > 5) {
                $logger->info('[Avatar/Describe] BLIP caption: ' . $caption);
                return new JsonResponse(['success' => true, 'description' => trim($caption), 'source' => 'blip']);
            }
        }

        // ── Fallback final : description générique ──
        $logger->warning('[Avatar/Describe] All vision APIs failed, using generic fallback');
        return new JsonResponse([
            'success'     => true,
            'description' => 'a person with a friendly face',
            'fallback'    => true,
        ]);
    }

    // =========================================================================
    //  ROUTE : /avatar/toonify  (CODE ORIGINAL — inchangé)
    // =========================================================================
    #[Route('/toonify', name: 'avatar_toonify', methods: ['POST'])]
    public function toonify(
        Request                $request,
        EntityManagerInterface $em,
        LoggerInterface        $logger
    ): JsonResponse {

        if (!$this->isCsrfTokenValid('avatar_toonify', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $description = trim($request->request->get('description', ''));
        if (strlen($description) < 3) {
            return new JsonResponse(['error' => 'Please enter a description (at least 3 characters)'], 400);
        }

        $hfToken = $_ENV['HUGGINGFACE_TOKEN'] ?? '';
        if (empty($hfToken)) {
            $logger->error('[Avatar] HUGGINGFACE_TOKEN missing in .env');
            return new JsonResponse(['error' => 'API token not configured. Add HUGGINGFACE_TOKEN to .env'], 500);
        }

        $style    = $request->request->get('style', 'anime');
        $template = self::STYLE_PROMPTS[$style] ?? self::STYLE_PROMPTS['anime'];
        $prompt   = str_replace('[DESC]', $description, $template);

        $logger->info('[Avatar] Style=' . $style . ' | Prompt=' . $prompt);

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

            if ($httpCode === 503) {
                $body = json_decode((string)$response, true);
                $wait = isset($body['estimated_time']) ? (int)ceil($body['estimated_time']) : 20;
                return new JsonResponse([
                    'error'         => 'Model is warming up, please retry in ' . $wait . 's.',
                    'model_loading' => true,
                    'retry_after'   => $wait,
                ], 503);
            }

            if ($httpCode === 429) {
                $lastError = 'Rate limit on ' . $apiUrl;
                $logger->warning('[Avatar] 429 rate limit, trying fallback...');
                continue;
            }

            if ($httpCode === 200 && strlen((string)$response) > 500) {
                $imageData = $response;
                break;
            }

            $body      = json_decode((string)$response, true);
            $lastError = $body['error'] ?? ('HTTP ' . $httpCode . ' from ' . $apiUrl);
            $logger->error('[Avatar] Error: ' . $lastError);
        }

        if ($imageData === null) {
            return new JsonResponse(['error' => $lastError . ' — Please retry in a few seconds.'], 500);
        }

        $isPng  = substr($imageData, 0, 4) === "\x89PNG";
        $isJpeg = substr($imageData, 0, 2) === "\xFF\xD8";
        $isWebp = substr($imageData, 8, 4) === 'WEBP';

        if (!$isPng && !$isJpeg && !$isWebp) {
            $body = json_decode((string)$imageData, true);
            $msg  = $body['error'] ?? 'Invalid image data received';
            $logger->error('[Avatar] Not a valid image: ' . $msg . ' | bytes: ' . bin2hex(substr($imageData, 0, 8)));
            return new JsonResponse(['error' => $msg . ' — Please retry.'], 500);
        }

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

    // =========================================================================
    //  ROUTE : /avatar/save  (CODE ORIGINAL — inchangé)
    // =========================================================================
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