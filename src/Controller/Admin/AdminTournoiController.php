<?php

namespace App\Controller\Admin;

use App\Repository\TournoiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tournoi')]
#[IsGranted('ROLE_ADMIN')]
class AdminTournoiController extends AbstractController
{
    #[Route('/', name: 'admin_tournoi_index')]
    public function index(TournoiRepository $tournoiRepository): Response
    {
        $tournois = $tournoiRepository->findAll();

        return $this->render('admin/tournoi/index.html.twig', [
            'tournois' => $tournois,
        ]);
    }
}
