<?php

namespace App\Controller\Admin;

use App\Entity\Matches;
use App\Entity\Tournoi;
use App\Form\MatchesType;
use App\Repository\MatchesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminMatchController extends AbstractController
{
    #[Route('/tournoi/{id}/matches', name: 'admin_tournoi_matches_index', methods: ['GET'])]
    public function index(Tournoi $tournoi): Response
    {
        return $this->render('admin/match/index.html.twig', [
            'tournoi' => $tournoi,
            'matches' => $tournoi->getMatchs(),
        ]);
    }

    #[Route('/tournoi/{id}/matches/create', name: 'admin_tournoi_matches_create', methods: ['GET', 'POST'])]
    public function create(Request $request, Tournoi $tournoi, EntityManagerInterface $entityManager): Response
    {
        $match = new Matches();
        $match->setTournoi($tournoi);
        
        $form = $this->createForm(MatchesType::class, $match);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($match);
            $entityManager->flush();

            $this->addFlash('success', 'Match added successfully!');

            return $this->redirectToRoute('admin_tournoi_matches_index', ['id' => $tournoi->getId()]);
        }

        return $this->render('admin/match/create.html.twig', [
            'tournoi' => $tournoi,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/matches/{id}', name: 'admin_matches_show', methods: ['GET'])]
    public function show(Matches $match): Response
    {
        return $this->render('admin/match/show.html.twig', [
            'match' => $match,
        ]);
    }

    #[Route('/matches/{id}/edit', name: 'admin_matches_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Matches $match, EntityManagerInterface $entityManager): Response
    {
        $tournoi = $match->getTournoi();
        $form = $this->createForm(MatchesType::class, $match);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Match updated successfully!');

            return $this->redirectToRoute('admin_tournoi_matches_index', ['id' => $tournoi->getId()]);
        }

        return $this->render('admin/match/edit.html.twig', [
            'match' => $match,
            'tournoi' => $tournoi,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/matches/{id}/delete', name: 'admin_matches_delete', methods: ['POST'])]
    public function delete(Request $request, Matches $match, EntityManagerInterface $entityManager): Response
    {
        $tournoiId = $match->getTournoi()->getId();

        if ($this->isCsrfTokenValid('delete-'.$match->getId(), $request->request->get('_token'))) {
            $entityManager->remove($match);
            $entityManager->flush();
            $this->addFlash('success', 'Match deleted successfully.');
        }

        return $this->redirectToRoute('admin_tournoi_matches_index', ['id' => $tournoiId]);
    }
}
