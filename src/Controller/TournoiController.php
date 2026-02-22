<?php

namespace App\Controller;

use App\Entity\Tournoi;
use App\Repository\TournoiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tournaments')]
#[IsGranted('ROLE_USER')]
class TournoiController extends AbstractController
{
    #[Route('/landing', name: 'app_tournoi_landing')]
    public function landing(): Response
    {
        return $this->render('tournoi/landing.html.twig');
    }

    #[Route('/', name: 'app_tournoi_index')]
    public function index(TournoiRepository $tournoiRepository): Response
    {
        return $this->render('tournoi/index.html.twig', [
            'tournaments' => $tournoiRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_tournoi_show')]
    public function show(Tournoi $tournoi): Response
    {
        return $this->render('tournoi/show.html.twig', [
            'tournament' => $tournoi,
            'matches' => $tournoi->getMatchs(),
        ]);
    }
}
