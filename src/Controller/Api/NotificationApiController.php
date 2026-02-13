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

    #[Route('/unread', name: 'api_notifications_unread', methods: ['GET'])]
    public function getUnread(): JsonResponse
    {
        $user = $this->getUser();
        
        $unread = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->orderBy('n.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        return new JsonResponse([
            'success' => true,
            'count' => count($unread),
            'notifications' => array_map(fn($n) => $this->formatNotification($n), $unread)
        ]);
    }

    #[Route('/check', name: 'api_check_notifications', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $user = $this->getUser();
        
        $unread = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->orderBy('n.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        $formatted = array_map(fn($n) => $this->formatNotification($n), $unread);
        
        return new JsonResponse([
            'success' => true,
            'unreadCount' => count($unread),
            'notifications' => $formatted
        ]);
    }
    
    #[Route('/{id}/mark-read', name: 'api_mark_notification_read', methods: ['POST'])]
    public function markRead(int $id): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);
        
        if (!$notification || $notification->getUser() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Not found'], 404);
        }
        
        $notification->setIsRead(true);
        $notification->setRead(true);
        $this->em->flush();
        
        return new JsonResponse(['success' => true]);
    }

    #[Route('/mark-all-read', name: 'api_mark_all_read', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        $user = $this->getUser();
        
        $this->notificationRepository->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.read', 'true')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
        
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