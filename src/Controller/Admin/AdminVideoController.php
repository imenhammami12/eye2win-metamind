<?php

namespace App\Controller\Admin;

use App\Repository\VideoRepository;
use App\Form\VideoAdminType;
use App\Form\AdminVideoUploadType;
use App\Service\VideoMatchService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Video;

#[Route('/admin/videos')]
class AdminVideoController extends AbstractController
{
    #[Route('', name: 'admin_videos_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, VideoRepository $videoRepository): Response
    {
        $search = $request->query->get('search', '');
        $statusFilter = $request->query->get('status', '');

        $queryBuilder = $videoRepository->createQueryBuilder('v')
            ->leftJoin('v.uploadedBy', 'u')
            ->addSelect('u')
            ->orderBy('v.uploadedAt', 'DESC');

        if ($search) {
            $queryBuilder->andWhere('v.title LIKE :search OR v.gameType LIKE :search OR u.username LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($statusFilter) {
            $queryBuilder->andWhere('v.status = :status')
                ->setParameter('status', $statusFilter);
        }

        $videos = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/videos/index.html.twig', [
            'videos' => $videos,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    #[Route('/{id}', name: 'admin_videos_show', requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(Video $video): Response
    {
        return $this->render('admin/videos/show.html.twig', [
            'video' => $video,
            'playerStats' => $video->getPlayerStats(),
        ]);
    }

    #[Route('/create', name: 'admin_videos_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, VideoMatchService $videoMatchService): Response
    {
        $form = $this->createForm(AdminVideoUploadType::class);
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
            $user = $form->get('user')->getData();

            if (!$user instanceof User) {
                $this->addFlash('error', 'Veuillez sélectionner un utilisateur.');
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'error' => 'Veuillez sélectionner un utilisateur.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                return $this->render('admin/videos/create.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $videoFile = $form->get('videoFile')->getData();

            if (!$videoFile) {
                $this->addFlash('error', 'Please select a video file.');
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'error' => 'Please select a video file.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                return $this->render('admin/videos/create.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            try {
                $videoMatchService->createFromUpload(
                    $user,
                    $form->get('title')->getData(),
                    $form->get('gameType')->getData(),
                    $videoFile,
                    (string) $form->get('visibility')->getData()
                );
            } catch (\Throwable $exception) {
                $message = 'Upload failed. Check Cloudinary configuration.';
                $this->addFlash('error', $message);

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
                }

                return $this->render('admin/videos/create.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $this->addFlash('success', 'Vidéo uploadée avec succès.');
            $redirectUrl = $this->generateUrl('admin_videos_index');

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['redirect' => $redirectUrl], Response::HTTP_CREATED);
            }

            return $this->redirectToRoute('admin_videos_index');
        }

        return $this->render('admin/videos/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_video_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Video $video, Request $request, VideoMatchService $videoMatchService): Response
    {
        $form = $this->createForm(VideoAdminType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $videoMatchService->updateVideo($video);
            $this->addFlash('success', 'Vidéo mise à jour.');

            return $this->redirectToRoute('admin_videos_index');
        }

        return $this->render('video/admin_edit.html.twig', [
            'form' => $form->createView(),
            'video' => $video,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_video_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Video $video, Request $request, VideoMatchService $videoMatchService): Response
    {
        if (!$this->isCsrfTokenValid('delete_video_' . $video->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_videos_index');
        }

        $videoMatchService->deleteVideo($video);
        $this->addFlash('success', 'Vidéo supprimée.');

        return $this->redirectToRoute('admin_videos_index');
    }
}
