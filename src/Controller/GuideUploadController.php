<?php

namespace App\Controller;

use App\Entity\GuideVideo;
use App\Form\GuideVideoType;
use App\Repository\GuideVideoRepository;
use App\Service\CloudinaryUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/guides/upload')]
#[IsGranted('ROLE_USER')]
class GuideUploadController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GuideVideoRepository $guideVideoRepository,
        private CloudinaryUploader $cloudinaryUploader,
    ) {
    }

    #[Route('', name: 'app_guide_upload')]
    public function create(Request $request): Response
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
            $guide->setStatus('pending'); // Guides are pending approval by default

            $videoFile = $form->get('videoFile')->getData();
            if ($videoFile) {
                try {
                    $upload = $this->cloudinaryUploader->uploadVideo($videoFile);
                } catch (\Throwable) {
                    $form->get('videoFile')->addError(new FormError('Video upload failed. Please check Cloudinary configuration and try again.'));

                    return $this->render('guides/upload.html.twig', [
                        'form' => $form->createView(),
                        'edit' => false,
                    ]);
                }

                if (empty($upload['secure_url'])) {
                    $form->get('videoFile')->addError(new FormError('Video upload failed. No Cloudinary URL returned.'));

                    return $this->render('guides/upload.html.twig', [
                        'form' => $form->createView(),
                        'edit' => false,
                    ]);
                }

                $guide->setVideoUrl($upload['secure_url']);
            }

            // Handle thumbnail upload
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

            $this->addFlash('success', 'Guide uploaded successfully! Awaiting admin approval.');

            return $this->redirectToRoute('app_my_guides');
        }

        return $this->render('guides/upload.html.twig', [
            'form' => $form->createView(),
            'edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_guide_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(GuideVideo $guide, Request $request): Response
    {
        // Check if user is the owner or admin
        if ($guide->getUploadedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You cannot edit this guide');
        }

        // Don't allow editing approved guides
        if ($guide->isApproved()) {
            $this->addFlash('warning', 'You cannot edit approved guides');
            return $this->redirectToRoute('app_my_guides');
        }

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
                $oldVideoUrl = $guide->getVideoUrl();
                if ($oldVideoUrl && $this->isLocalGuideVideo($oldVideoUrl)) {
                    $oldVideoFile = $this->getParameter('kernel.project_dir') . '/public' . $oldVideoUrl;
                    if (file_exists($oldVideoFile)) {
                        unlink($oldVideoFile);
                    }
                }

                try {
                    $upload = $this->cloudinaryUploader->uploadVideo($videoFile);
                } catch (\Throwable) {
                    $form->get('videoFile')->addError(new FormError('Video upload failed. Please check Cloudinary configuration and try again.'));

                    return $this->render('guides/upload.html.twig', [
                        'form' => $form->createView(),
                        'guide' => $guide,
                        'edit' => true,
                    ]);
                }

                if (empty($upload['secure_url'])) {
                    $form->get('videoFile')->addError(new FormError('Video upload failed. No Cloudinary URL returned.'));

                    return $this->render('guides/upload.html.twig', [
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

                // Delete old thumbnail
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
            $this->addFlash('success', 'Guide updated successfully!');

            return $this->redirectToRoute('app_my_guides');
        }

        return $this->render('guides/upload.html.twig', [
            'form' => $form->createView(),
            'guide' => $guide,
            'edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_guide_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(GuideVideo $guide, Request $request): Response
    {
        if ($guide->getUploadedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You cannot delete this guide');
        }

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

            $this->addFlash('success', 'Guide deleted successfully');
        }

        return $this->redirectToRoute('app_my_guides');
    }

    #[Route('/my-guides', name: 'app_my_guides')]
    public function myGuides(): Response
    {
        $guides = $this->guideVideoRepository->findByUploader($this->getUser());

        return $this->render('guides/my-guides.html.twig', [
            'guides' => $guides,
        ]);
    }

    private function isLocalGuideVideo(string $videoUrl): bool
    {
        return str_starts_with($videoUrl, '/uploads/guide-videos/');
    }
}
