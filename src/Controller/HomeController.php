<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(VideoRepository $videoRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $videos = [];

        if ($user instanceof User) {
            $videos = $videoRepository->findByUser($user);
        }

        return $this->render('home/dashboard.html.twig', [
            'videos' => $videos,
        ]);
    }
        #[Route('/planning', name: 'app_planning')]
    public function planning(): Response
    {
        return $this->render('home/planning.html.twig');
    }
}