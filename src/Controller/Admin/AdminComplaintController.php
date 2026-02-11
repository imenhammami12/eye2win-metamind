<?php

namespace App\Controller\Admin;

use App\Entity\Complaint;
use App\Entity\ComplaintStatus;
use App\Entity\ComplaintPriority;
use App\Entity\User;
use App\Entity\AuditLog;
use App\Repository\ComplaintRepository;
use App\Repository\UserRepository;
use App\Service\ComplaintNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\NotificationService;

#[Route('/admin/complaints')]
#[IsGranted('ROLE_ADMIN')]
class AdminComplaintController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'admin_complaints_index')]
    public function index(
        Request $request,
        ComplaintRepository $complaintRepository,
        PaginatorInterface $paginator
    ): Response {
        $search = $request->query->get('search', '');
        $statusFilter = $request->query->get('status', '');
        $priorityFilter = $request->query->get('priority', '');
        $categoryFilter = $request->query->get('category', '');
        
        $queryBuilder = $complaintRepository->createQueryBuilder('c')
            ->leftJoin('c.submittedBy', 'u')
            ->leftJoin('c.assignedTo', 'a')
            ->addSelect('u', 'a')
            ->orderBy('c.priority', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC');
        
        // Search
        if ($search) {
            $queryBuilder->andWhere('c.subject LIKE :search OR c.description LIKE :search OR u.username LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        // Filter by status
        if ($statusFilter) {
            $queryBuilder->andWhere('c.status = :status')
                ->setParameter('status', $statusFilter);
        }
        
        // Filter by priority
        if ($priorityFilter) {
            $queryBuilder->andWhere('c.priority = :priority')
                ->setParameter('priority', $priorityFilter);
        }
        
        // Filter by category
        if ($categoryFilter) {
            $queryBuilder->andWhere('c.category = :category')
                ->setParameter('category', $categoryFilter);
        }
        
        // Pagination
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );
        
        // Statistics
        $stats = $complaintRepository->getStatistics();
        $stats['unassigned'] = count($complaintRepository->findUnassigned());
        $stats['avg_resolution_hours'] = $complaintRepository->getAverageResolutionTime();
        
        return $this->render('admin/complaints/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'priorityFilter' => $priorityFilter,
            'categoryFilter' => $categoryFilter,
            'stats' => $stats,
        ]);
    }
    
    #[Route('/{id}', name: 'admin_complaints_show', requirements: ['id' => '\d+'])]
    public function show(Complaint $complaint, UserRepository $userRepository): Response
    {
        // Get all users with ROLE_ADMIN
        // Method 1: Try repository method
        $admins = [];
        
        try {
            $admins = $userRepository->findAdmins();
        } catch (\Exception $e) {
            // Method 2: Fallback - get all users and filter manually
            try {
                $allUsers = $userRepository->findAll();
                foreach ($allUsers as $user) {
                    if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                        $admins[] = $user;
                    }
                }
                // Sort by username
                usort($admins, function($a, $b) {
                    return strcmp($a->getUsername(), $b->getUsername());
                });
            } catch (\Exception $e2) {
                // Last resort: empty array
                $admins = [];
            }
        }
        
        // Debug: Add flash message to see how many admins were found
        if (empty($admins)) {
            $this->addFlash('warning', 'No administrators found in the system. Please check user roles.');
        }
        
        return $this->render('admin/complaints/show.html.twig', [
            'complaint' => $complaint,
            'admins' => $admins,
        ]);
    }
    
    #[Route('/{id}/assign', name: 'admin_complaints_assign', methods: ['POST'])]
    public function assign(
        Request $request,
        Complaint $complaint,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('assign-complaint-' . $complaint->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        
        $adminId = $request->request->get('admin_id');
        
        if ($adminId === 'unassign') {
            $previousAdmin = $complaint->getAssignedTo();
            $complaint->setAssignedTo(null);
            
            $this->createAuditLog(
                $em,
                'COMPLAINT_UNASSIGNED',
                'Complaint',
                $complaint->getId(),
                "Complaint #{$complaint->getId()} unassigned from " . ($previousAdmin ? $previousAdmin->getUsername() : 'unknown')
            );
            
            $this->addFlash('success', 'Complaint unassigned successfully');
        } else {
            $admin = $userRepository->find($adminId);
            
            if (!$admin || !in_array('ROLE_ADMIN', $admin->getRoles())) {
                $this->addFlash('error', 'Invalid administrator selected');
                return $this->redirectToRoute('admin_complaints_show', ['id' => $complaint->getId()]);
            }
            
            $complaint->setAssignedTo($admin);
            
            // Auto-change status to IN_PROGRESS if it's PENDING
            if ($complaint->getStatus() === ComplaintStatus::PENDING) {
                $complaint->setStatus(ComplaintStatus::IN_PROGRESS);
            }
            
            $this->createAuditLog(
                $em,
                'COMPLAINT_ASSIGNED',
                'Complaint',
                $complaint->getId(),
                "Complaint #{$complaint->getId()} assigned to {$admin->getUsername()}"
            );
            
            // Send notification
            $this->notificationService->notifyComplaintAssigned($complaint, $admin);
            
            $this->addFlash('success', "Complaint assigned to {$admin->getUsername()} successfully");
        }
        
        $em->flush();
    
        $this->notificationService->notifyComplaintAssigned($complaint, $admin);

        return $this->redirectToRoute('admin_complaints_show', ['id' => $complaint->getId()]);
    }
    
    #[Route('/{id}/update-status', name: 'admin_complaints_update_status', methods: ['POST'])]
    public function updateStatus(
        Request $request,
        Complaint $complaint,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('update-status-' . $complaint->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        
        $newStatus = ComplaintStatus::from($request->request->get('status'));
        $oldStatus = $complaint->getStatus();
        
        $complaint->setStatus($newStatus);
        
        $this->createAuditLog(
            $em,
            'COMPLAINT_STATUS_CHANGED',
            'Complaint',
            $complaint->getId(),
            "Status changed from {$oldStatus->value} to {$newStatus->value}"
        );
        
        // Send notification
        $this->notificationService->notifyComplaintStatusChanged($complaint, $oldStatus->getLabel(), $newStatus->getLabel());
        
        $em->flush();
           $this->notificationService->notifyComplaintStatusChanged(
            $complaint,
            $oldStatus->getLabel(),
            $newStatus->getLabel()
        );
        $this->addFlash('success', "Complaint status updated to {$newStatus->getLabel()}");
        return $this->redirectToRoute('admin_complaints_show', ['id' => $complaint->getId()]);
    }
    
    #[Route('/{id}/update-priority', name: 'admin_complaints_update_priority', methods: ['POST'])]
    public function updatePriority(
        Request $request,
        Complaint $complaint,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('update-priority-' . $complaint->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        
        $newPriority = ComplaintPriority::from($request->request->get('priority'));
        $oldPriority = $complaint->getPriority();
        
        $complaint->setPriority($newPriority);
        
        $this->createAuditLog(
            $em,
            'COMPLAINT_PRIORITY_CHANGED',
            'Complaint',
            $complaint->getId(),
            "Priority changed from {$oldPriority->value} to {$newPriority->value}"
        );
        
        // Send notification
        $this->notificationService->notifyComplaintPriorityChanged($complaint, $oldPriority->getLabel(), $newPriority->getLabel());
        
        $em->flush();
        
         $this->notificationService->notifyComplaintPriorityChanged(
            $complaint,
            $oldPriority->getLabel(),
            $newPriority->getLabel()
        );
        $this->addFlash('success', "Complaint priority updated to {$newPriority->getLabel()}");
        return $this->redirectToRoute('admin_complaints_show', ['id' => $complaint->getId()]);
    }
    
    #[Route('/{id}/respond', name: 'admin_complaints_respond', methods: ['POST'])]
    public function respond(
        Request $request,
        Complaint $complaint,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('respond-' . $complaint->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        
        $response = $request->request->get('response');
        
        if (empty(trim($response))) {
            $this->addFlash('error', 'Response cannot be empty');
            return $this->redirectToRoute('admin_complaints_show', ['id' => $complaint->getId()]);
        }
        
        $complaint->setAdminResponse($response);
        
        // Auto-assign to current admin if not assigned
        if (!$complaint->getAssignedTo()) {
            $complaint->setAssignedTo($this->getUser());
        }
        
        // Change status to IN_PROGRESS if PENDING
        if ($complaint->getStatus() === ComplaintStatus::PENDING) {
            $complaint->setStatus(ComplaintStatus::IN_PROGRESS);
        }
        
        $this->createAuditLog(
            $em,
            'COMPLAINT_RESPONDED',
            'Complaint',
            $complaint->getId(),
            "Admin response added to complaint #{$complaint->getId()}"
        );
        
        // Send notification
        $this->notificationService->notifyComplaintResponded($complaint);
        
        $em->flush();
        
        $this->notificationService->notifyComplaintResponded($complaint);

        $this->addFlash('success', 'Response submitted successfully');
        return $this->redirectToRoute('admin_complaints_show', ['id' => $complaint->getId()]);
    }
    
    #[Route('/{id}/resolve', name: 'admin_complaints_resolve', methods: ['POST'])]
    public function resolve(
        Request $request,
        Complaint $complaint,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('resolve-' . $complaint->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        
        $resolutionNotes = $request->request->get('resolution_notes');
        
        if (empty(trim($resolutionNotes))) {
            $this->addFlash('error', 'Resolution notes are required');
            return $this->redirectToRoute('admin_complaints_show', ['id' => $complaint->getId()]);
        }
        
        $complaint->setResolutionNotes($resolutionNotes);
        $complaint->setStatus(ComplaintStatus::RESOLVED);
        
        $this->createAuditLog(
            $em,
            'COMPLAINT_RESOLVED',
            'Complaint',
            $complaint->getId(),
            "Complaint #{$complaint->getId()} marked as resolved"
        );
        
        // Send notification
        $this->notificationService->notifyComplaintResolved($complaint);
        
        $em->flush();
        $this->notificationService->notifyComplaintResolved($complaint);

        $this->addFlash('success', 'Complaint resolved successfully');
        return $this->redirectToRoute('admin_complaints_show', ['id' => $complaint->getId()]);
    }
    
    #[Route('/{id}/delete', name: 'admin_complaints_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Complaint $complaint,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('delete-' . $complaint->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        
        $complaintId = $complaint->getId();
        
        $this->createAuditLog(
            $em,
            'COMPLAINT_DELETED',
            'Complaint',
            $complaintId,
            "Complaint #{$complaintId} deleted by administrator"
        );
        
        $em->remove($complaint);
        $em->flush();
        
        $this->addFlash('success', 'Complaint deleted successfully');
        return $this->redirectToRoute('admin_complaints_index');
    }
    
    private function createAuditLog(
        EntityManagerInterface $em,
        string $action,
        string $entityType,
        ?int $entityId,
        string $details
    ): void {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        
        $auditLog = new AuditLog();
        $auditLog->setUser($currentUser);
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setDetails($details);
        $auditLog->setIpAddress($_SERVER['REMOTE_ADDR'] ?? null);
        
        $em->persist($auditLog);
    }
}