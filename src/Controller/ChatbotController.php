<?php

namespace App\Controller;

use App\Service\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChatbotController extends AbstractController
{
    #[Route('/api/chatbot/ask', name: 'api_chatbot_ask', methods: ['POST'])]
    public function ask(Request $request, ChatbotService $chatbotService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['question']) || empty(trim($data['question']))) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Question is required'
                ], 400);
            }
            
            $question = $data['question'];
            $response = $chatbotService->getResponse($question);
            
            return new JsonResponse([
                'success' => true,
                'response' => $response
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Une erreur est survenue. Veuillez réessayer.'
            ], 500);
        }
    }
}
