<?php

namespace App\Controller\Admin;

use App\Entity\TrainingSession;
use App\Repository\TrainingSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/session')]
class AdminTrainingSessionController extends AbstractController
{
    #[Route('/{id}', name: 'admin_session_show', methods: ['GET'])]
    public function show(TrainingSession $session): Response
    {
        return $this->render('admin/session/show.html.twig', [
            'session' => $session,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_session_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TrainingSession $session, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $status = $request->request->get('status');
            if ($status) {
                $session->setStatus($status);
                $em->flush();
                
                $this->addFlash('success', 'Training session updated successfully');
                return $this->redirectToRoute('admin_planning_sessions', ['id' => $session->getPlanning()->getId()]);
            }
        }

        return $this->render('admin/session/edit.html.twig', [
            'session' => $session,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_session_delete', methods: ['POST'])]
    public function delete(Request $request, TrainingSession $session, EntityManagerInterface $em): Response
    {
        $planningId = $session->getPlanning()->getId();
        
        if ($this->isCsrfTokenValid('delete-session-' . $session->getId(), $request->request->get('_token'))) {
            $em->remove($session);
            $em->flush();
            
            $this->addFlash('success', 'Training session deleted successfully');
        }

        return $this->redirectToRoute('admin_planning_sessions', ['id' => $planningId]);
    }
}
