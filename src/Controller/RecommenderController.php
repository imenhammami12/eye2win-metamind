<?php

namespace App\Controller;

use App\Service\SessionRecommenderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RecommenderController extends AbstractController
{
    #[Route('/api/recommander-sessions', name: 'app_api_recommender', methods: ['GET'])]
    public function getRecommendations(SessionRecommenderService $recommenderService): JsonResponse
    {
        $recommendations = $recommenderService->getRecommendations();
        
        return new JsonResponse([
            'content' => $recommendations
        ]);
    }
}
