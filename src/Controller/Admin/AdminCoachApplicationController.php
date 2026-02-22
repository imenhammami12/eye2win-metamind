<?php

namespace App\Controller\Admin;

use App\Entity\CoachApplication;
use App\Entity\ApplicationStatus;
use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Repository\CoachApplicationRepository;
use App\Service\CoachApplicationEmailService;
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
    public function __construct(
        private CoachApplicationEmailService $emailService
    ) {
    }
    
    #[Route('/', name: 'admin_coach_applications_index')]
    public function index(
        Request $request,
        CoachApplicationRepository $repository
    ): Response {
        // 🔍 FILTRES MULTIPLES
        $statusFilter = $request->query->get('status', '');
        $search = $request->query->get('search', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');
        $sortBy = $request->query->get('sort_by', 'submittedAt');
        $sortOrder = $request->query->get('sort_order', 'DESC');
        
        $queryBuilder = $repository->createQueryBuilder('ca')
            ->leftJoin('ca.user', 'u')
            ->addSelect('u');
        
        // Filtre par statut
        if ($statusFilter) {
            $queryBuilder->andWhere('ca.status = :status')
                ->setParameter('status', $statusFilter);
        }
        
        // Recherche par nom, username, email
        if ($search) {
            $queryBuilder->andWhere(
                'u.username LIKE :search OR u.email LIKE :search OR u.fullName LIKE :search OR ca.certifications LIKE :search OR ca.experience LIKE :search'
            )
            ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre par date de soumission (début)
        if ($dateFrom) {
            try {
                $dateFromObj = new \DateTime($dateFrom . ' 00:00:00');
                $queryBuilder->andWhere('ca.submittedAt >= :dateFrom')
                    ->setParameter('dateFrom', $dateFromObj);
            } catch (\Exception $e) {
                // Date invalide, on ignore
            }
        }
        
        // Filtre par date de soumission (fin)
        if ($dateTo) {
            try {
                $dateToObj = new \DateTime($dateTo . ' 23:59:59');
                $queryBuilder->andWhere('ca.submittedAt <= :dateTo')
                    ->setParameter('dateTo', $dateToObj);
            } catch (\Exception $e) {
                // Date invalide, on ignore
            }
        }
        
        // Tri
        $validSortFields = ['submittedAt', 'reviewedAt', 'status'];
        if (in_array($sortBy, $validSortFields)) {
            $validSortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? strtoupper($sortOrder) : 'DESC';
            $queryBuilder->orderBy('ca.' . $sortBy, $validSortOrder);
        } else {
            $queryBuilder->orderBy('ca.submittedAt', 'DESC');
        }
        
        // Tri secondaire par nom d'utilisateur
        $queryBuilder->addOrderBy('u.username', 'ASC');
        
        $applications = $queryBuilder->getQuery()->getResult();
        
        // Statistics
        $stats = [
            'pending' => $repository->count(['status' => ApplicationStatus::PENDING]),
            'approved' => $repository->count(['status' => ApplicationStatus::APPROVED]),
            'rejected' => $repository->count(['status' => ApplicationStatus::REJECTED]),
            'total' => $repository->count([]),
        ];
        
        return $this->render('admin/coach_applications/index.html.twig', [
            'applications' => $applications,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
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
    
    #[Route('/{id}/approve', name: 'admin_coach_applications_approve', methods: ['POST'])]
    public function approve(
        Request $request,
        CoachApplication $application,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('approve-' . $application->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        
        $comment = $request->request->get('comment', '');
        
        // Approve the application (automatically adds COACH role)
        $application->approve($comment);
        
        // Create notification for the user
        $notification = new Notification();
        $notification->setUser($application->getUser());
        $notification->setType(NotificationType::COACH_APPROVED);
        $notification->setMessage('Congratulations! Your coach application has been approved.');
        $notification->setLink('/profile');
        
        $em->persist($notification);
        
        $this->createAuditLog(
            $em,
            'COACH_APPLICATION_APPROVED',
            'CoachApplication',
            $application->getId(),
            "Application from " . $application->getUser()->getUsername() . " approved"
        );
        
        $em->flush();
        
        // Send approval email
        $this->emailService->sendApprovalEmail($application);
        
        $this->addFlash('success', 'The application has been approved, the user is now a coach and has received a confirmation email');
        return $this->redirectToRoute('admin_coach_applications_show', ['id' => $application->getId()]);
    }
    
    #[Route('/{id}/reject', name: 'admin_coach_applications_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        CoachApplication $application,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('reject-' . $application->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        
        $comment = $request->request->get('comment');
        
        if (!$comment) {
            $this->addFlash('error', 'A comment is required to reject an application');
            return $this->redirectToRoute('admin_coach_applications_show', ['id' => $application->getId()]);
        }
        
        // Reject the application
        $application->reject($comment);
        
        // Create notification for the user
        $notification = new Notification();
        $notification->setUser($application->getUser());
        $notification->setType(NotificationType::COACH_REJECTED);
        $notification->setMessage('Your coach application has been rejected. Check the details for more information.');
        $notification->setLink('/profile');
        
        $em->persist($notification);
        
        $this->createAuditLog(
            $em,
            'COACH_APPLICATION_REJECTED',
            'CoachApplication',
            $application->getId(),
            "Application from " . $application->getUser()->getUsername() . " rejected: $comment"
        );
        
        $em->flush();
        
        // Send rejection email
        $this->emailService->sendRejectionEmail($application);
        
        $this->addFlash('warning', 'The application has been rejected and the user has been notified by email');
        return $this->redirectToRoute('admin_coach_applications_show', ['id' => $application->getId()]);
    }
    
    #[Route('/coaches', name: 'admin_coaches_list')]
    public function coachesList(EntityManagerInterface $em): Response
    {
        // Get all users with COACH role
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