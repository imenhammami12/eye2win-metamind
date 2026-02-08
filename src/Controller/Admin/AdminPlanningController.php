<?php

namespace App\Controller\Admin;

use App\Entity\Planning;
use App\Entity\PlanningLevel;
use App\Entity\PlanningType;
use App\Repository\PlanningRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/planning')]
#[IsGranted('ROLE_ADMIN')]
class AdminPlanningController extends AbstractController
{
    #[Route('/', name: 'admin_planning_index')]
    public function index(Request $request, PlanningRepository $planningRepository): Response
    {
        $search = $request->query->get('search', '');
        $typeFilter = $request->query->get('type', '');
        $levelFilter = $request->query->get('level', '');
        $sortBy = $request->query->get('sort', 'date');
        $sortOrder = $request->query->get('order', 'DESC');
        
        // Validate sort parameters
        $validSortFields = ['date', 'time', 'localisation', 'type', 'level'];
        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'date';
        }
        
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'DESC';
        }
        
        $queryBuilder = $planningRepository->createQueryBuilder('p')
            ->orderBy('p.' . $sortBy, $sortOrder);
            
        // Add secondary sort by time if sorting by date
        if ($sortBy === 'date') {
            $queryBuilder->addOrderBy('p.time', $sortOrder);
        }
        
        // Search by description or localisation
        if ($search) {
            $queryBuilder->andWhere('p.description LIKE :search OR p.localisation LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        // Filter by Type
        if ($typeFilter) {
            $queryBuilder->andWhere('p.type = :type')
                ->setParameter('type', $typeFilter);
        }
        
        // Filter by Level
        if ($levelFilter) {
            $queryBuilder->andWhere('p.level = :level')
                ->setParameter('level', $levelFilter);
        }
        
        $plannings = $queryBuilder->getQuery()->getResult();
        
        return $this->render('admin/planning/index.html.twig', [
            'plannings' => $plannings,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'levelFilter' => $levelFilter,
            'planningTypes' => PlanningType::cases(),
            'planningLevels' => PlanningLevel::cases(),
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_planning_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Planning $planning,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('delete-' . $planning->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $em->remove($planning);
        $em->flush();
        
        $this->addFlash('success', 'Planning supprimé avec succès');
        return $this->redirectToRoute('admin_planning_index');
    }
    #[Route('/addPlan', name: 'admin_planning_create')]
    public function create(
        Request $request, 
        EntityManagerInterface $em,
        \Symfony\Component\String\Slugger\SluggerInterface $slugger
    ): Response {
        $planning = new Planning();
        $form = $this->createForm(\App\Form\PlanningType::class, $planning);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/planning',
                        $newFilename
                    );
                    $planning->setImage($newFilename);
                } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image');
                }
            }

            $em->persist($planning);
            $em->flush();

            $this->addFlash('success', 'Planning créé avec succès');
            return $this->redirectToRoute('admin_planning_index');
        }

        return $this->render('admin/planning/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/{id}/update', name: 'admin_planning_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Planning $planning, 
        EntityManagerInterface $em,
        \Symfony\Component\String\Slugger\SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(\App\Form\PlanningType::class, $planning);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/planning',
                        $newFilename
                    );
                    $planning->setImage($newFilename);
                } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image');
                }
            }

            $em->flush();

            $this->addFlash('success', 'Planning modifié avec succès');
            return $this->redirectToRoute('admin_planning_index');
        }

        return $this->render('admin/planning/edit.html.twig', [
            'planning' => $planning,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/afficher', name: 'admin_planning_show', methods: ['GET'])]
    public function show(Planning $planning): Response
    {
        return $this->render('admin/planning/show.html.twig', [
            'planning' => $planning,
        ]);
    }

    #[Route('/{id}/sessions', name: 'admin_planning_sessions', methods: ['GET'])]
    public function sessions(Planning $planning): Response
    {
        return $this->render('admin/planning/sessions.html.twig', [
            'planning' => $planning,
            'sessions' => $planning->getTrainingSessions(),
        ]);
    }
}
