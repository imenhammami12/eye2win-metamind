<?php

namespace App\Controller;

use App\Entity\Agent;
use App\Entity\Game;
use App\Entity\GuideVideo;
use App\Form\AgentType;
use App\Form\GameType;
use App\Form\GuideVideoType;
use App\Repository\AgentRepository;
use App\Repository\GameRepository;
use App\Repository\GuideVideoRepository;
use App\Service\CloudinaryUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/guides')]
#[IsGranted('ROLE_ADMIN')]
class GuideDashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private AgentRepository $agentRepository,
        private GuideVideoRepository $guideVideoRepository,
        private CloudinaryUploader $cloudinaryUploader,
    ) {
    }

    #[Route('', name: 'app_guide_dashboard')]
    public function index(): Response
    {
        $pendingCount = $this->guideVideoRepository->countPendingGuides();
        $recentGuides = $this->guideVideoRepository->findPendingGuides();
        $popularGuides = $this->guideVideoRepository->findPopularGuides(10);
        $games = $this->gameRepository->findAllOrderedByName();

        return $this->render('admin/guides/dashboard.html.twig', [
            'pendingCount' => $pendingCount,
            'recentGuides' => $recentGuides,
            'popularGuides' => $popularGuides,
            'games' => $games,
        ]);
    }

    #[Route('/pending', name: 'app_guide_pending')]
    public function pendingGuides(): Response
    {
        $guides = $this->guideVideoRepository->findPendingGuides();

        return $this->render('admin/guides/pending.html.twig', [
            'guides' => $guides,
        ]);
    }

    #[Route('/manage', name: 'app_guide_manage_videos')]
    public function manageVideos(): Response
    {
        $guides = $this->guideVideoRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/guides/manage-videos.html.twig', [
            'guides' => $guides,
        ]);
    }

    #[Route('/guide/new', name: 'app_guide_admin_new')]
    public function newGuide(Request $request): Response
    {
        $guide = new GuideVideo();
        $form = $this->createForm(GuideVideoType::class, $guide);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $videoFile = $form->get('videoFile')->getData();
            $videoUrl = trim((string) $guide->getVideoUrl());

            if (!$videoFile && $videoUrl === '') {
                $form->get('videoUrl')->addError(new FormError('Please provide either a video URL or upload a video file.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $guide->setUploadedBy($this->getUser());
            $guide->setStatus('approved');
            $guide->setApprovedAt(new \DateTime());

            $videoFile = $form->get('videoFile')->getData();
            if ($videoFile) {
                try {
                    $upload = $this->cloudinaryUploader->uploadVideo($videoFile);
                } catch (\Throwable) {
                    $form->get('videoFile')->addError(new FormError('Video upload failed. Please check Cloudinary configuration and try again.'));

                    return $this->render('admin/guides/guide-form.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                if (empty($upload['secure_url'])) {
                    $form->get('videoFile')->addError(new FormError('Video upload failed. No Cloudinary URL returned.'));

                    return $this->render('admin/guides/guide-form.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $guide->setVideoUrl($upload['secure_url']);
            }

            $thumbnailFile = $form->get('thumbnailFile')->getData();
            if ($thumbnailFile) {
                $thumbnailPath = $this->getParameter('kernel.project_dir') . '/public/uploads/guides/';
                if (!is_dir($thumbnailPath)) {
                    mkdir($thumbnailPath, 0755, true);
                }

                $filename = uniqid('thumb_') . '.' . $thumbnailFile->guessExtension();
                $thumbnailFile->move($thumbnailPath, $filename);
                $guide->setThumbnail('/uploads/guides/' . $filename);
            }

            $this->entityManager->persist($guide);
            $this->entityManager->flush();

            $this->addFlash('success', 'Guide vidéo ajouté avec succès.');

            return $this->redirectToRoute('app_guide_dashboard');
        }

        return $this->render('admin/guides/guide-form.html.twig', [
            'form' => $form->createView(),
            'edit' => false,
        ]);
    }

    #[Route('/guide/{id}/edit', name: 'app_guide_admin_edit')]
    public function editGuide(GuideVideo $guide, Request $request): Response
    {
        $form = $this->createForm(GuideVideoType::class, $guide);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $videoFile = $form->get('videoFile')->getData();
            $videoUrl = trim((string) $guide->getVideoUrl());

            if (!$videoFile && $videoUrl === '') {
                $form->get('videoUrl')->addError(new FormError('Please provide either a video URL or upload a video file.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $videoFile = $form->get('videoFile')->getData();
            if ($videoFile) {
                try {
                    $upload = $this->cloudinaryUploader->uploadVideo($videoFile);
                } catch (\Throwable) {
                    $form->get('videoFile')->addError(new FormError('Video upload failed. Please check Cloudinary configuration and try again.'));

                    return $this->render('admin/guides/guide-form.html.twig', [
                        'form' => $form->createView(),
                        'guide' => $guide,
                        'edit' => true,
                    ]);
                }

                if (empty($upload['secure_url'])) {
                    $form->get('videoFile')->addError(new FormError('Video upload failed. No Cloudinary URL returned.'));

                    return $this->render('admin/guides/guide-form.html.twig', [
                        'form' => $form->createView(),
                        'guide' => $guide,
                        'edit' => true,
                    ]);
                }

                $guide->setVideoUrl($upload['secure_url']);
            }

            $thumbnailFile = $form->get('thumbnailFile')->getData();
            if ($thumbnailFile) {
                $thumbnailPath = $this->getParameter('kernel.project_dir') . '/public/uploads/guides/';
                if (!is_dir($thumbnailPath)) {
                    mkdir($thumbnailPath, 0755, true);
                }

                if ($guide->getThumbnail()) {
                    $oldFile = $this->getParameter('kernel.project_dir') . '/public' . $guide->getThumbnail();
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $filename = uniqid('thumb_') . '.' . $thumbnailFile->guessExtension();
                $thumbnailFile->move($thumbnailPath, $filename);
                $guide->setThumbnail('/uploads/guides/' . $filename);
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Guide video updated successfully.');

            return $this->redirectToRoute('app_guide_manage_videos');
        }

        return $this->render('admin/guides/guide-form.html.twig', [
            'form' => $form->createView(),
            'guide' => $guide,
            'edit' => true,
        ]);
    }

    #[Route('/{id}/approve', name: 'app_guide_approve', methods: ['POST'])]
    public function approveGuide(GuideVideo $guide, Request $request): Response
    {
        if ($this->isCsrfTokenValid('approve' . $guide->getId(), $request->request->get('_token'))) {
            $guide->setStatus('approved');
            $guide->setApprovedAt(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Guide "%s" approved!', $guide->getTitle()));
        }

        return $this->redirectToPreviousGuidePage($request);
    }

    #[Route('/{id}/reject', name: 'app_guide_reject', methods: ['POST'])]
    public function rejectGuide(GuideVideo $guide, Request $request): Response
    {
        if ($this->isCsrfTokenValid('reject' . $guide->getId(), $request->request->get('_token'))) {
            $guide->setStatus('rejected');
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Guide "%s" rejected!', $guide->getTitle()));
        }

        return $this->redirectToPreviousGuidePage($request);
    }

    #[Route('/{id}/delete', name: 'app_guide_admin_delete', methods: ['POST'])]
    public function deleteGuide(GuideVideo $guide, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $guide->getId(), $request->request->get('_token'))) {
            if ($guide->getVideoUrl() && $this->isLocalGuideVideo($guide->getVideoUrl())) {
                $videoFile = $this->getParameter('kernel.project_dir') . '/public' . $guide->getVideoUrl();
                if (file_exists($videoFile)) {
                    unlink($videoFile);
                }
            }

            // Delete thumbnail
            if ($guide->getThumbnail()) {
                $file = $this->getParameter('kernel.project_dir') . '/public' . $guide->getThumbnail();
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $this->entityManager->remove($guide);
            $this->entityManager->flush();

            $this->addFlash('success', 'Guide deleted');
        }

        return $this->redirectToPreviousGuidePage($request);
    }

    // ===== GAMES MANAGEMENT =====

    #[Route('/games', name: 'app_game_list')]
    public function listGames(): Response
    {
        $games = $this->gameRepository->findAllOrderedByName();

        return $this->render('admin/guides/games-list.html.twig', [
            'games' => $games,
        ]);
    }

    #[Route('/game/new', name: 'app_game_new')]
    public function newGame(Request $request): Response
    {
        $game = new Game();
        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($game);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Game "%s" created!', $game->getName()));

            return $this->redirectToRoute('app_game_list');
        }

        return $this->render('admin/guides/game-form.html.twig', [
            'form' => $form->createView(),
            'game' => null,
        ]);
    }

    #[Route('/game/{id}/edit', name: 'app_game_edit')]
    public function editGame(Game $game, Request $request): Response
    {
        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Game "%s" updated!', $game->getName()));

            return $this->redirectToRoute('app_game_list');
        }

        return $this->render('admin/guides/game-form.html.twig', [
            'form' => $form->createView(),
            'game' => $game,
        ]);
    }

    #[Route('/game/{id}/delete', name: 'app_game_delete', methods: ['POST'])]
    public function deleteGame(Game $game, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $game->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($game);
            $this->entityManager->flush();

            $this->addFlash('success', 'Game deleted');
        }

        return $this->redirectToRoute('app_game_list');
    }

    // ===== AGENTS MANAGEMENT =====

    #[Route('/agents', name: 'app_agent_list')]
    public function listAgents(): Response
    {
        $agents = $this->agentRepository->findAll();

        return $this->render('admin/guides/agents-list.html.twig', [
            'agents' => $agents,
        ]);
    }

    #[Route('/agent/new', name: 'app_agent_new')]
    public function newAgent(Request $request): Response
    {
        $agent = new Agent();
        $form = $this->createForm(AgentType::class, $agent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($agent);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Agent "%s" created!', $agent->getName()));

            return $this->redirectToRoute('app_agent_list');
        }

        return $this->render('admin/guides/agent-form.html.twig', [
            'form' => $form->createView(),
            'agent' => null,
        ]);
    }

    #[Route('/agent/{id}/edit', name: 'app_agent_edit')]
    public function editAgent(Agent $agent, Request $request): Response
    {
        $form = $this->createForm(AgentType::class, $agent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Agent "%s" updated!', $agent->getName()));

            return $this->redirectToRoute('app_agent_list');
        }

        return $this->render('admin/guides/agent-form.html.twig', [
            'form' => $form->createView(),
            'agent' => $agent,
        ]);
    }

    #[Route('/agent/{id}/delete', name: 'app_agent_delete', methods: ['POST'])]
    public function deleteAgent(Agent $agent, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $agent->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($agent);
            $this->entityManager->flush();

            $this->addFlash('success', 'Agent deleted');
        }

        return $this->redirectToRoute('app_agent_list');
    }

    private function isLocalGuideVideo(string $videoUrl): bool
    {
        return str_starts_with($videoUrl, '/uploads/guide-videos/');
    }

    private function redirectToPreviousGuidePage(Request $request): Response
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_guide_pending');
    }
}
