<?php

namespace App\Service;

use App\Entity\Complaint;
use App\Entity\CoachApplication;
use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    // ==================== COMPLAINT NOTIFICATIONS ====================
    
    public function notifyComplaintSubmitted(Complaint $complaint): void
    {
        // Notify user
        $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_SUBMITTED,
            'Your support ticket has been submitted successfully',
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );

        // Notify all admins
        $admins = $this->getAdmins();
        foreach ($admins as $admin) {
            $this->createNotification(
                $admin,
                NotificationType::COMPLAINT_NEW,
                sprintf('New complaint: "%s" from %s', 
                    $complaint->getSubject(), 
                    $complaint->getSubmittedBy()->getUsername()
                ),
                $this->urlGenerator->generate('admin_complaints_show', ['id' => $complaint->getId()])
            );
        }
    }

    public function notifyComplaintAssigned(Complaint $complaint, User $assignedAdmin): void
    {
        // Notify user
        $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_UPDATED,
            'Your complaint has been assigned to our support team',
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );

        // Notify assigned admin
        $this->createNotification(
            $assignedAdmin,
            NotificationType::COMPLAINT_ASSIGNED,
            sprintf('Complaint assigned to you: "%s"', $complaint->getSubject()),
            $this->urlGenerator->generate('admin_complaints_show', ['id' => $complaint->getId()])
        );
    }

    public function notifyComplaintResponded(Complaint $complaint): void
    {
        $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_RESPONDED,
            sprintf('New response to your complaint: "%s"', $complaint->getSubject()),
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );
    }

    public function notifyComplaintStatusChanged(Complaint $complaint, string $oldStatus, string $newStatus): void
    {
        $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_UPDATED,
            sprintf('Complaint status changed: %s → %s', $oldStatus, $newStatus),
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );
    }

    public function notifyComplaintResolved(Complaint $complaint): void
    {
        $this->createNotification(
            $complaint->getSubmittedBy(),
            NotificationType::COMPLAINT_RESOLVED,
            sprintf('Your complaint has been resolved: "%s"', $complaint->getSubject()),
            $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
        );
    }

    public function notifyComplaintPriorityChanged(Complaint $complaint, string $oldPriority, string $newPriority): void
    {
        // Only notify if priority increased
        $levels = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3, 'URGENT' => 4];
        if (($levels[$newPriority] ?? 0) > ($levels[$oldPriority] ?? 0)) {
            $this->createNotification(
                $complaint->getSubmittedBy(),
                NotificationType::COMPLAINT_UPDATED,
                sprintf('Complaint priority increased to %s', $newPriority),
                $this->urlGenerator->generate('app_complaints_show', ['id' => $complaint->getId()])
            );
        }
    }

    // ==================== COACH APPLICATION NOTIFICATIONS ====================

    public function notifyCoachApplicationSubmitted(CoachApplication $application): void
    {
        // Notify user
        $this->createNotification(
            $application->getUser(),
            NotificationType::COACH_APPLICATION_STATUS,
            'Your coach application has been submitted and is under review',
            $this->urlGenerator->generate('user_profile')
        );

        // Notify all admins
        $admins = $this->getAdmins();
        foreach ($admins as $admin) {
            $this->createNotification(
                $admin,
                NotificationType::COACH_APPLICATION,
                sprintf('New coach application from %s', $application->getUser()->getUsername()),
                $this->urlGenerator->generate('admin_coach_applications_show', ['id' => $application->getId()])
            );
        }
    }

    public function notifyCoachApplicationApproved(CoachApplication $application): void
    {
        $this->createNotification(
            $application->getUser(),
            NotificationType::COACH_APPROVED,
            '🎉 Your coach application has been approved!',
            $this->urlGenerator->generate('user_profile')
        );
    }

    public function notifyCoachApplicationRejected(CoachApplication $application): void
    {
        $this->createNotification(
            $application->getUser(),
            NotificationType::COACH_REJECTED,
            'Your coach application has been reviewed. Please check the details.',
            $this->urlGenerator->generate('user_profile')
        );
    }

    // ==================== HELPER METHODS ====================

    private function createNotification(User $user, NotificationType $type, string $message, ?string $link = null): void
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
    }

    private function getAdmins(): array
    {
        try {
            return $this->entityManager->getRepository(User::class)->findAdmins();
        } catch (\Exception $e) {
            // Fallback
            $allUsers = $this->entityManager->getRepository(User::class)->findAll();
            return array_filter($allUsers, fn(User $user) => in_array('ROLE_ADMIN', $user->getRoles(), true));
        }
    }
}