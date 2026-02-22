<?php

namespace App\Controller;

use App\Entity\Agent;
use App\Entity\Game;
use App\Entity\GuideVideo;
use App\Repository\AgentRepository;
use App\Repository\GameRepository;
use App\Repository\GuideVideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/guides')]
class GuideController extends AbstractController
{
    public function __construct(
        private GameRepository $gameRepository,
        private AgentRepository $agentRepository,
        private GuideVideoRepository $guideVideoRepository,
    ) {
    }

    #[Route('', name: 'app_guides')]
    public function index(): Response
    {
        $games = $this->gameRepository->findAllOrderedByName();

        return $this->render('guides/games-selection.html.twig', [
            'games' => $games,
        ]);
    }

    #[Route('/{gameSlug}', name: 'app_guides_game', requirements: ['gameSlug' => '^(?!upload$|my-guides$).*'])]
    public function showGame(string $gameSlug): Response
    {
        $game = $this->gameRepository->findBySlug($gameSlug);

        if (!$game) {
            throw $this->createNotFoundException('Game not found');
        }

        $agents = $this->agentRepository->findByGame($game);

        return $this->render('guides/agents-list.html.twig', [
            'game' => $game,
            'agents' => $agents,
        ]);
    }

    #[Route('/{gameSlug}/{agentSlug}', name: 'app_guides_agent')]
    public function showAgent(string $gameSlug, string $agentSlug): Response
    {
        $game = $this->gameRepository->findBySlug($gameSlug);

        if (!$game) {
            throw $this->createNotFoundException('Game not found');
        }

        $agent = $this->agentRepository->findByGameAndSlug($game, $agentSlug);

        if (!$agent) {
            throw $this->createNotFoundException('Agent not found');
        }

        // Get all approved guides for this agent
        $guides = $this->guideVideoRepository->findApprovedByGameAndAgent($game, $agent);

        // Extract unique maps from guides
        $maps = ['All'];
        foreach ($guides as $guide) {
            if ($guide->getMap() !== 'All' && !in_array($guide->getMap(), $maps)) {
                $maps[] = $guide->getMap();
            }
        }

        return $this->render('guides/agent-videos.html.twig', [
            'game' => $game,
            'agent' => $agent,
            'guides' => $guides,
            'maps' => $maps,
        ]);
    }
}
