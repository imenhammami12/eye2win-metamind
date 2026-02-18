<?php
// src/Controller/SpeechController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/speech')]
#[IsGranted('ROLE_USER')]
class SpeechController extends AbstractController
{
    #[Route('/transcribe', name: 'app_speech_transcribe', methods: ['POST'])]
    public function transcribe(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $audioFile = $request->files->get('audio');

        if (!$audioFile) {
            return $this->json(['error' => 'No audio received'], 400);
        }

        $token     = $this->getParameter('app.huggingface_token');
        $audioData = file_get_contents($audioFile->getPathname());

        // Correct URL from official HF docs — raw binary audio, returns {"text": "..."}
        $url = 'https://router.huggingface.co/hf-inference/models/openai/whisper-large-v3';

        try {
            $response = $httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'audio/webm',
                    'x-use-cache'   => '0',
                ],
                'body'    => $audioData,
                'timeout' => 60,
            ]);

            $statusCode = $response->getStatusCode();
            $raw        = $response->getContent(false);

            // Log raw response for debugging
            error_log('[STT] Status: ' . $statusCode . ' | Response: ' . substr($raw, 0, 300));

            // Model cold-starting
            if ($statusCode === 503) {
                $data = json_decode($raw, true) ?? [];
                return $this->json(['retry_after' => (int)($data['estimated_time'] ?? 20)], 503);
            }

            if ($statusCode !== 200) {
                return $this->json(['error' => $raw], 500);
            }

            $data = json_decode($raw, true);

            // HF returns {"text": "..."} for ASR models
            if (isset($data['text'])) {
                return $this->json(['text' => trim($data['text'])]);
            }

            return $this->json(['error' => 'Unexpected response: ' . $raw], 500);

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}