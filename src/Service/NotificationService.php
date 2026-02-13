<?php

namespace App\Service;

use App\Entity\Complaint;
use App\Entity\CoachApplication;
use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private ?HubInterface $hub = null
    ) {}

    // ==================== COMPLAINT NOTIFICATIONS ====================
    
    public function notifyComplaintSubmitted(Complaint $complaint): void
    {
        // Notify user
        $userNotif = $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_SUBMITTED,
            'Your support ticket has been submitted successfully',
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );
        $this->publishNotification($userNotif);

        // Notify all admins
        $admins = $this->getAdmins();
        foreach ($admins as $admin) {
            $adminNotif = $this->createNotification(
                $admin,
                NotificationType::COMPLAINT_NEW,
                sprintf('New complaint: "%s" from %s', 
                    $complaint->getSubject(), 
                    $complaint->getSubmittedBy()->getUsername()
                ),
                $this->urlGenerator->generate('admin_complaints_show', ['id' => $complaint->getId()])
            );
            $this->publishNotification($adminNotif);
        }
    }

    public function notifyComplaintAssigned(Complaint $complaint, User $assignedAdmin): void
    {
        $userNotif = $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_UPDATED,
            'Your complaint has been assigned to our support team',
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );
        $this->publishNotification($userNotif);

        $adminNotif = $this->createNotification(
            $assignedAdmin,
            NotificationType::COMPLAINT_ASSIGNED,
            sprintf('Complaint assigned to you: "%s"', $complaint->getSubject()),
            $this->urlGenerator->generate('admin_complaints_show', ['id' => $complaint->getId()])
        );
        $this->publishNotification($adminNotif);
    }

    public function notifyComplaintResponded(Complaint $complaint): void
    {
        $notif = $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_RESPONDED,
            sprintf('New response to your complaint: "%s"', $complaint->getSubject()),
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );
        $this->publishNotification($notif);
    }

    public function notifyComplaintStatusChanged(Complaint $complaint, string $oldStatus, string $newStatus): void
    {
        $notif = $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_UPDATED,
            sprintf('Complaint status changed: %s → %s', $oldStatus, $newStatus),
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );
        $this->publishNotification($notif);
    }

    public function notifyComplaintResolved(Complaint $complaint): void
    {
        $notif = $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_RESOLVED,
            sprintf('Your complaint has been resolved: "%s"', $complaint->getSubject()),
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );
        $this->publishNotification($notif);
    }

    public function notifyComplaintPriorityChanged(Complaint $complaint, string $oldPriority, string $newPriority): void
    {
        $levels = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3, 'URGENT' => 4];
        if (($levels[$newPriority] ?? 0) > ($levels[$oldPriority] ?? 0)) {
            $notif = $this->createNotification(
                $complaint->getSubmittedBy(),
                NotificationType::COMPLAINT_UPDATED,
                sprintf('Complaint priority increased to %s', $newPriority),
                $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
            );
            $this->publishNotification($notif);
        }
    }

    // ==================== COACH APPLICATION NOTIFICATIONS ====================

    public function notifyCoachApplicationSubmitted(CoachApplication $application): void
    {
        $userNotif = $this->createNotification(
            $application->getUser(),
            NotificationType::COACH_APPLICATION_STATUS,
            'Your coach application has been submitted and is under review',
            $this->urlGenerator->generate('user_profile')
        );
        $this->publishNotification($userNotif);

        $admins = $this->getAdmins();
        foreach ($admins as $admin) {
            $adminNotif = $this->createNotification(
                $admin,
                NotificationType::COACH_APPLICATION,
                sprintf('New coach application from %s', $application->getUser()->getUsername()),
                $this->urlGenerator->generate('admin_coach_applications_show', ['id' => $application->getId()])
            );
            $this->publishNotification($adminNotif);
        }
    }

    public function notifyCoachApplicationApproved(CoachApplication $application): void
    {
        $notif = $this->createNotification(
            $application->getUser(),
            NotificationType::COACH_APPROVED,
            '🎉 Your coach application has been approved!',
            $this->urlGenerator->generate('user_profile')
        );
        $this->publishNotification($notif);
    }

    public function notifyCoachApplicationRejected(CoachApplication $application): void
    {
        $notif = $this->createNotification(
            $application->getUser(),
            NotificationType::COACH_REJECTED,
            'Your coach application has been reviewed. Please check the details.',
            $this->urlGenerator->generate('user_profile')
        );
        $this->publishNotification($notif);
    }

    // ==================== HELPER METHODS ====================

    private function createNotification(User $user, NotificationType $type, string $message, ?string $link = null): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setLink($link);
        $notification->setIsRead(false);
        $notification->setRead(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    private function publishNotification(Notification $notification): void
    {
        if (!$this->hub) {
            return; // Mercure not configured
        }

        $diff = time() - $notification->getCreatedAt()->getTimestamp();
        $timeAgo = $diff < 60 ? 'just now' : floor($diff/60) . ' min ago';

        $data = [
            'id' => $notification->getId(),
            'message' => $notification->getMessage(),
            'icon' => $notification->getType()->getIcon(),
            'link' => $notification->getLink(),
            'isRead' => $notification->isRead(),
            'timeAgo' => $timeAgo,
            'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s')
        ];

        $update = new Update(
            'notifications/user/' . $notification->getUser()->getId(),
            json_encode($data)
        );

        $this->hub->publish($update);
    }

    private function getAdmins(): array
    {
        try {
            return $this->entityManager->getRepository(User::class)->findAdmins();
        } catch (\Exception $e) {
            $allUsers = $this->entityManager->getRepository(User::class)->findAll();
            return array_filter($allUsers, fn(User $user) => in_array('ROLE_ADMIN', $user->getRoles(), true));
        }
    }
}