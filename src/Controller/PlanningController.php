<?php

namespace App\Controller;

use App\Entity\Planning;
use App\Entity\TrainingSession;
use App\Repository\PlanningRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlanningController extends AbstractController
{
    #[Route('/planing', name: 'app_planning_index')]
<<<<<<< HEAD
    public function index(PlanningRepository $planningRepository): Response
    {
        $user = $this->getUser();
        $plannings = $planningRepository->findAll();
=======
    public function index(PlanningRepository $planningRepository, Request $request): Response
    {
        $user = $this->getUser();
        $sortBy = $request->query->get('sort', 'date');
        $sortOrder = $request->query->get('order', 'DESC');
        
        // Validate sort parameters
        $validSortFields = ['date', 'time'];
        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'date';
        }
        
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'DESC';
        }
        
        // Get all plannings with sorting
        $queryBuilder = $planningRepository->createQueryBuilder('p')
            ->orderBy('p.' . $sortBy, $sortOrder);
        
        // Add secondary sort by time if sorting by date
        if ($sortBy === 'date') {
            $queryBuilder->addOrderBy('p.time', 'ASC');
        }
        
        $plannings = $queryBuilder->getQuery()->getResult();
>>>>>>> computer-vision
        
        // Get user's existing sessions to check which plannings they've already joined
        $userSessionPlanningIds = [];
        if ($user) {
            foreach ($user->getTrainingSessions() as $session) {
                $userSessionPlanningIds[] = $session->getPlanning()->getId();
            }
        }
        
        return $this->render('planning/index.html.twig', [
            'plannings' => $plannings,
            'userSessionPlanningIds' => $userSessionPlanningIds,
<<<<<<< HEAD
=======
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
>>>>>>> computer-vision
        ]);
    }

    #[Route('/planing/join/{id}', name: 'app_planning_join')]
    public function join(Planning $planning, Request $request, EntityManagerInterface $em): Response
    {
        // Deny access if not logged in (though firewall might handle this, explicit check is good)
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Check if already joined (optional but good UX)
        // $existingSession = ...

        if ($request->isMethod('POST')) {
            $user = $this->getUser();
            
            $session = new TrainingSession();
            $session->setPlanning($planning);
            $session->setUser($user);
            // Status and JoinedAt are set by default/constructor logic or here
            $session->setStatus('en attente');
            $session->setJoinedAt(new \DateTime());

            $em->persist($session);
            $em->flush();

            $this->addFlash('success', 'You have successfully joined the session!');

            return $this->redirectToRoute('app_planning_index');
        }

        return $this->render('planning/join.html.twig', [
            'planning' => $planning,
        ]);
    }

    #[Route('/my-sessions', name: 'app_my_sessions')]
    public function mySessions(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        $sessions = $user->getTrainingSessions();
        
        return $this->render('planning/my-sessions.html.twig', [
            'sessions' => $sessions,
        ]);
    }

    #[Route('/my-sessions/cancel/{id}', name: 'app_cancel_session', methods: ['POST'])]
    public function cancelSession(TrainingSession $session, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        // Verify the session belongs to the current user
        if ($session->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You cannot cancel this session.');
        }
        
        if ($this->isCsrfTokenValid('cancel-session-' . $session->getId(), $request->request->get('_token'))) {
            $em->remove($session);
            $em->flush();
            
            $this->addFlash('success', 'Session cancelled successfully!');
        }
        
        return $this->redirectToRoute('app_my_sessions');
    }
}
