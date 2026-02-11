<?php

namespace App\Controller\Api;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationApiController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('/check', name: 'api_check_notifications', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $user = $this->getUser();
        
        // Get unread notifications
        $unread = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->orderBy('n.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
        
        // Get recently read
        $read = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = true')
            ->orderBy('n.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        $all = array_merge($unread, $read);
        
        // Format notifications
        $formatted = array_map(fn($n) => $this->formatNotification($n), $all);
        
        // Find new ones (< 1 minute old)
        $new = array_filter($formatted, fn($n) => (time() - strtotime($n['createdAt'])) < 60);
        
        return new JsonResponse([
            'success' => true,
            'newNotifications' => array_values($new),
            'unreadCount' => count($unread),
            'allNotifications' => $formatted
        ]);
    }
    
    #[Route('/{id}/mark-read', name: 'api_mark_notification_read', methods: ['POST'])]
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
        
        return [
            'id' => $n->getId(),
            'message' => $n->getMessage(),
            'icon' => $n->getType()->getIcon(),
            'link' => $n->getLink(),
            'isRead' => $n->isRead(),
            'timeAgo' => $timeAgo,
            'createdAt' => $n->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }
}