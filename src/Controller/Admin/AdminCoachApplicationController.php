<?php

namespace App\Controller\Admin;

use App\Entity\CoachApplication;
use App\Entity\ApplicationStatus;
use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Repository\CoachApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/coach-applications')]
#[IsGranted('ROLE_ADMIN')]
class AdminCoachApplicationController extends AbstractController
{
    #[Route('/', name: 'admin_coach_applications_index')]
    public function index(
        Request $request,
        CoachApplicationRepository $repository
    ): Response {
        $statusFilter = $request->query->get('status', '');
        
        $queryBuilder = $repository->createQueryBuilder('ca')
            ->leftJoin('ca.user', 'u')
            ->addSelect('u')
            ->orderBy('ca.submittedAt', 'DESC');
        
        if ($statusFilter) {
            $queryBuilder->andWhere('ca.status = :status')
                ->setParameter('status', $statusFilter);
        }
        
        $applications = $queryBuilder->getQuery()->getResult();
        
        // Statistiques
        $stats = [
            'pending' => $repository->count(['status' => ApplicationStatus::PENDING]),
            'underReview' => $repository->count(['status' => ApplicationStatus::UNDER_REVIEW]),
            'approved' => $repository->count(['status' => ApplicationStatus::APPROVED]),
            'rejected' => $repository->count(['status' => ApplicationStatus::REJECTED]),
        ];
        
        return $this->render('admin/coach_applications/index.html.twig', [
            'applications' => $applications,
            'statusFilter' => $statusFilter,
            'stats' => $stats,
            'applicationStatuses' => ApplicationStatus::cases(),
        ]);
    }
    
    #[Route('/{id}', name: 'admin_coach_applications_show', requirements: ['id' => '\d+'])]
    public function show(CoachApplication $application): Response
    {
        return $this->render('admin/coach_applications/show.html.twig', [
            'application' => $application,
        ]);
    }
    
    #[Route('/{id}/review', name: 'admin_coach_applications_review', methods: ['POST'])]
    public function review(
        Request $request,
        CoachApplication $application,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('review-' . $application->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $application->setStatus(ApplicationStatus::UNDER_REVIEW);
        
        $this->createAuditLog(
            $em,
            'COACH_APPLICATION_UNDER_REVIEW',
            'CoachApplication',
            $application->getId(),
            "Demande de " . $application->getUser()->getUsername() . " mise en révision"
        );
        
        $em->flush();
        
        $this->addFlash('info', 'La demande est maintenant en cours de révision');
        return $this->redirectToRoute('admin_coach_applications_show', ['id' => $application->getId()]);
    }
    
    #[Route('/{id}/approve', name: 'admin_coach_applications_approve', methods: ['POST'])]
    public function approve(
        Request $request,
        CoachApplication $application,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('approve-' . $application->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $comment = $request->request->get('comment', '');
        
        // Approuver la demande (cela ajoute automatiquement le rôle COACH)
        $application->approve($comment);
        
        // Créer une notification pour l'utilisateur
        $notification = new Notification();
        $notification->setUser($application->getUser());
        $notification->setType(NotificationType::COACH_APPROVED);
        $notification->setMessage('Félicitations ! Votre demande pour devenir coach a été approuvée.');
        $notification->setLink('/profile');
        
        $em->persist($notification);
        
        $this->createAuditLog(
            $em,
            'COACH_APPLICATION_APPROVED',
            'CoachApplication',
            $application->getId(),
            "Demande de " . $application->getUser()->getUsername() . " approuvée"
        );
        
        $em->flush();
        
        $this->addFlash('success', 'La demande a été approuvée et l\'utilisateur est maintenant coach');
        return $this->redirectToRoute('admin_coach_applications_show', ['id' => $application->getId()]);
    }
    
    #[Route('/{id}/reject', name: 'admin_coach_applications_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        CoachApplication $application,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('reject-' . $application->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $comment = $request->request->get('comment');
        
        if (!$comment) {
            $this->addFlash('error', 'Un commentaire est requis pour rejeter une demande');
            return $this->redirectToRoute('admin_coach_applications_show', ['id' => $application->getId()]);
        }
        
        // Rejeter la demande
        $application->reject($comment);
        
        // Créer une notification pour l'utilisateur
        $notification = new Notification();
        $notification->setUser($application->getUser());
        $notification->setType(NotificationType::COACH_REJECTED);
        $notification->setMessage('Votre demande pour devenir coach a été rejetée. Consultez les détails pour plus d\'informations.');
        $notification->setLink('/profile');
        
        $em->persist($notification);
        
        $this->createAuditLog(
            $em,
            'COACH_APPLICATION_REJECTED',
            'CoachApplication',
            $application->getId(),
            "Demande de " . $application->getUser()->getUsername() . " rejetée : $comment"
        );
        
        $em->flush();
        
        $this->addFlash('warning', 'La demande a été rejetée');
        return $this->redirectToRoute('admin_coach_applications_show', ['id' => $application->getId()]);
    }
    
    #[Route('/coaches', name: 'admin_coaches_list')]
    public function coachesList(EntityManagerInterface $em): Response
    {
        // Récupérer tous les utilisateurs ayant le rôle COACH
        $coaches = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\User', 'u')
            ->where('u.rolesJson LIKE :role')
            ->setParameter('role', '%ROLE_COACH%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/coach_applications/coaches_list.html.twig', [
            'coaches' => $coaches,
        ]);
    }
    
    private function createAuditLog(
        EntityManagerInterface $em,
        string $action,
        string $entityType,
        ?int $entityId,
        string $details
    ): void {
        $auditLog = new AuditLog();
        $auditLog->setUser($this->getUser());
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setDetails($details);
        $auditLog->setIpAddress($_SERVER['REMOTE_ADDR'] ?? null);
        
        $em->persist($auditLog);
    }
}