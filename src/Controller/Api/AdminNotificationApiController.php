<?php

namespace App\Controller\Api;

use App\Entity\NotificationType;
use App\Repository\NotificationRepository;
use App\Repository\ComplaintRepository;
use App\Repository\CoachApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/notifications')]
#[IsGranted('ROLE_ADMIN')]
class AdminNotificationApiController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private ComplaintRepository $complaintRepository,
        private CoachApplicationRepository $coachApplicationRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('/check', name: 'api_admin_check_notifications', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $user = $this->getUser();
        
        $types = [
            NotificationType::COMPLAINT_NEW,
            NotificationType::COMPLAINT_ASSIGNED,
            NotificationType::COACH_APPLICATION,
        ];
        
        $notifications = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->andWhere('n.type IN (:types)')
            ->setParameter('user', $user)
            ->setParameter('types', array_map(fn($t) => $t->value, $types))
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(15)
            ->getQuery()
            ->getResult();
        
        $formatted = array_map(fn($n) => $this->formatNotification($n), $notifications);
        $new = array_filter($formatted, fn($n) => (time() - strtotime($n['createdAt'])) < 60);
        
        return new JsonResponse([
            'success' => true,
            'notifications' => $formatted,
            'newNotifications' => array_values($new),
            'unreadCount' => count($notifications)
        ]);
    }
    
    #[Route('/stats', name: 'api_admin_notification_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $pendingComplaints = $this->complaintRepository->countPending();
        $pendingCoach = $this->coachApplicationRepository->count(['status' => 'PENDING']);
        
        return new JsonResponse([
            'success' => true,
            'stats' => [
                'pendingComplaints' => $pendingComplaints,
                'pendingCoachApplications' => $pendingCoach,
                'totalPending' => $pendingComplaints + $pendingCoach
            ]
        ]);
    }
    
    #[Route('/{id}/mark-read', name: 'api_admin_mark_notification_read', methods: ['POST'])]
    public function markRead(int $id): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);
        
        if (!$notification || $notification->getUser() !== $this->getUser()) {
            return new JsonResponse(['success' => false], 404);
        }
        
        $notification->setIsRead(true);
        $notification->setRead(true);
        $this->em->flush();
        
        return new JsonResponse(['success' => true]);
    }
    
    private function formatNotification($n): array
    {
        $diff = time() - $n->getCreatedAt()->getTimestamp();
        
        if ($diff < 60) $timeAgo = 'just now';
        elseif ($diff < 3600) $timeAgo = floor($diff/60) . ' min ago';
        elseif ($diff < 86400) $timeAgo = floor($diff/3600) . 'h ago';
        else $timeAgo = floor($diff/86400) . 'd ago';
        
        $titles = [
            'COMPLAINT_NEW' => 'New Complaint',
            'COMPLAINT_ASSIGNED' => 'Complaint Assigned',
            'COACH_APPLICATION' => 'Coach Application'
        ];
        
        return [
            'id' => $n->getId(),
            'title' => $titles[$n->getType()->value] ?? 'Notification',
            'message' => $n->getMessage(),
            'icon' => $n->getType()->getIcon(),
            'link' => $n->getLink(),
            'type' => $n->getType()->value,
            'isRead' => $n->isRead(),
            'timeAgo' => $timeAgo,
            'createdAt' => $n->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }
}