<?php

namespace App\Controller\Admin;

use App\Entity\Tournoi;
use App\Form\TournoiType;
use App\Repository\TournoiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

use App\Entity\TypeTournoi;

#[Route('/admin/tournoi')]
#[IsGranted('ROLE_ADMIN')]
class AdminTournoiController extends AbstractController
{
    #[Route('/', name: 'admin_tournoi_index')]
    public function index(Request $request, TournoiRepository $tournoiRepository): Response
    {
        $search = $request->query->get('search');
        $type = $request->query->get('type');
        $sort = $request->query->get('sort', 'dateDebut');
        $direction = $request->query->get('direction', 'DESC');

        // Allow sorting by these fields
        $allowedSorts = ['nom', 'dateDebut', 'dateFin', 'typeTournoi'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'dateDebut';
        }

        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $tournois = $tournoiRepository->findBySearchAndFilter($search, $type, $sort, $direction);

        return $this->render('admin/tournoi/index.html.twig', [
            'tournois' => $tournois,
            'search' => $search,
            'typeFilter' => $type,
            'sort' => $sort,
            'direction' => $direction,
            'types' => TypeTournoi::cases(),
        ]);
    }

    #[Route('/create', name: 'admin_tournoi_create')]
    public function create(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $tournoi = new Tournoi();
        $form = $this->createForm(TournoiType::class, $tournoi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('tournois_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                    $this->addFlash('error', 'Error uploading image');
                }

                $tournoi->setImage($newFilename);
            }

            $entityManager->persist($tournoi);
            $entityManager->flush();

            $this->addFlash('success', 'Tournoi created successfully');

            return $this->redirectToRoute('admin_tournoi_index');
        }

        return $this->render('admin/tournoi/create.html.twig', [
            'tournoi' => $tournoi,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_tournoi_show', methods: ['GET'])]
    public function show(Tournoi $tournoi): Response
    {
        return $this->render('admin/tournoi/show.html.twig', [
            'tournoi' => $tournoi,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_tournoi_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tournoi $tournoi, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(TournoiType::class, $tournoi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('tournois_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading image');
                }

                $tournoi->setImage($newFilename);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Tournoi updated successfully');

            return $this->redirectToRoute('admin_tournoi_index');
        }

        return $this->render('admin/tournoi/edit.html.twig', [
            'tournoi' => $tournoi,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_tournoi_delete', methods: ['POST'])]
    public function delete(Request $request, Tournoi $tournoi, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-'.$tournoi->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tournoi);
            $entityManager->flush();
            $this->addFlash('success', 'Tournoi deleted successfully');
        }

        return $this->redirectToRoute('admin_tournoi_index');
    }
}
