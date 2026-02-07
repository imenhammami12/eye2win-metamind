<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use App\Form\VideoAdminType;
use App\Form\VideoUploadType;
use App\Repository\VideoRepository;
use App\Service\VideoMatchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VideoMatchController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(VideoRepository $videoRepository): Response
    {
        $user = $this->getUser();
        $videos = [];

        if ($user instanceof User) {
            $videos = $videoRepository->findByUser($user);
            $videos = array_slice($videos, 0, 6);
        }

        return $this->render('home/index.html.twig', [
            'videos' => $videos,
        ]);
    }

    #[Route('/upload', name: 'app_video_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request, VideoMatchService $videoMatchService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $form = $this->createForm(VideoUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            if (!$user instanceof User) {
                throw $this->createAccessDeniedException('User not authenticated.');
            }

            $videoFile = $form->get('videoFile')->getData();

            if (!$videoFile) {
                $this->addFlash('error', 'Veuillez sélectionner un fichier vidéo.');
                return $this->render('video/upload.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $video = $videoMatchService->createFromUpload(
                $user,
                $form->get('title')->getData(),
                $form->get('gameType')->getData(),
                $videoFile
            );

            $this->addFlash('success', 'Vidéo uploadée avec succès.');

            return $this->redirectToRoute('app_video_show', ['id' => $video->getId()]);
        }

        return $this->render('video/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/video/{id}', name: 'app_video_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Video $video): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not authenticated.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $video->getUploadedBy() !== $user) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        return $this->render('video/show.html.twig', [
            'video' => $video,
            'playerStats' => $video->getPlayerStats(),
        ]);
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(VideoRepository $videoRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $videos = $videoRepository->findBy([], ['uploadedAt' => 'DESC']);

        return $this->render('home/dashboard.html.twig', [
            'videos' => $videos,
        ]);
    }

    #[Route('/dashboard/video/{id}/edit', name: 'app_admin_video_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function adminEdit(Video $video, Request $request, VideoMatchService $videoMatchService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(VideoAdminType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $videoMatchService->updateVideo($video);
            $this->addFlash('success', 'Vidéo mise à jour.');

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('video/admin_edit.html.twig', [
            'form' => $form->createView(),
            'video' => $video,
        ]);
    }

    #[Route('/dashboard/video/{id}/delete', name: 'app_admin_video_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminDelete(Video $video, Request $request, VideoMatchService $videoMatchService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_video_' . $video->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_dashboard');
        }

        $videoMatchService->deleteVideo($video);
        $this->addFlash('success', 'Vidéo supprimée.');

        return $this->redirectToRoute('app_dashboard');
    }
}
