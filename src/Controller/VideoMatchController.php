<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use App\Form\VideoUploadType;
use App\Repository\VideoRepository;
use App\Service\VideoMatchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
            if ($this->isGranted('ROLE_ADMIN')) {
                $videos = $videoRepository->findBy([], ['uploadedAt' => 'DESC']);
            } else {
                $videos = $videoRepository->findVisibleForUser($user);
            }
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

        if ($form->isSubmitted() && !$form->isValid()) {
            if ($request->isXmlHttpRequest()) {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }

                return new JsonResponse([
                    'error' => 'Formulaire invalide.',
                    'details' => $errors,
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            if (!$user instanceof User) {
                throw $this->createAccessDeniedException('User not authenticated.');
            }

            $videoFile = $form->get('videoFile')->getData();

            if (!$videoFile) {
                $this->addFlash('error', 'Veuillez sélectionner un fichier vidéo.');
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'error' => 'Veuillez sélectionner un fichier vidéo.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                return $this->render('video/upload.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            try {
                $video = $videoMatchService->createFromUpload(
                    $user,
                    $form->get('title')->getData(),
                    $form->get('gameType')->getData(),
                    $videoFile,
                    (string) $form->get('visibility')->getData()
                );
            } catch (\Throwable $exception) {
                $message = 'Échec de l\'upload. Vérifiez la configuration Cloudinary.';
                $this->addFlash('error', $message);

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
                }

                return $this->render('video/upload.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $this->addFlash('success', 'Vidéo uploadée avec succès.');
            $redirectUrl = $this->generateUrl('app_video_show', ['id' => $video->getId()]);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['redirect' => $redirectUrl], Response::HTTP_CREATED);
            }

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

}
