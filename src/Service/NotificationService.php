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
use Psr\Log\LoggerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private ?HubInterface $hub = null,
        private ?LoggerInterface $logger = null
    ) {}

    // ==================== COMPLAINT NOTIFICATIONS ====================
    
    public function notifyComplaintSubmitted(Complaint $complaint): void
    {
        $this->log('🔔 notifyComplaintSubmitted called for complaint ID: ' . $complaint->getId());
        
        // ✅ PAS de notification à l'utilisateur - il sait déjà qu'il a soumis sa réclamation
        // Il voit déjà un message flash de succès : "Your complaint has been submitted successfully"

        // Notify all admins - CRITICAL SECTION
        $this->log('👥 Getting admins...');
        $admins = $this->getAdmins();
        $this->log('👥 Found ' . count($admins) . ' admins');
        
        if (empty($admins)) {
            $this->log('⚠️ WARNING: No admins found! Cannot send admin notifications.');
            return;
        }
        
        foreach ($admins as $admin) {
            $this->log('📧 Creating notification for admin: ' . $admin->getUsername() . ' (ID: ' . $admin->getId() . ')');
            
            $adminNotif = $this->createNotification(
                $admin,
                NotificationType::COMPLAINT_NEW,
                sprintf('New complaint: "%s" from %s', 
                    $complaint->getSubject(), 
                    $complaint->getSubmittedBy()->getUsername()
                ),
                $this->urlGenerator->generate('admin_complaints_show', ['id' => $complaint->getId()])
            );
            
            $this->log('✅ Admin notification created: ' . $adminNotif->getId());
            $this->publishNotification($adminNotif);
            $this->log('📡 Notification published to Mercure for admin ID: ' . $admin->getId());
        }
        
        $this->log('🎉 All notifications sent!');
    }

    public function notifyComplaintAssigned(Complaint $complaint, User $assignedAdmin): void
    {
        $this->log('🔔 notifyComplaintAssigned called');
        
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
        $this->log('🔔 notifyCoachApplicationSubmitted called for application ID: ' . $application->getId());
        
        // ✅ PAS de notification à l'utilisateur - il sait déjà qu'il a soumis sa demande
        // Il voit déjà un message flash de succès

        // Notify all admins
        $this->log('👥 Getting admins for coach application...');
        $admins = $this->getAdmins();
        $this->log('👥 Found ' . count($admins) . ' admins');
        
        if (empty($admins)) {
            $this->log('⚠️ WARNING: No admins found!');
            return;
        }
        
        foreach ($admins as $admin) {
            $this->log('📧 Creating coach application notification for admin: ' . $admin->getUsername());
            
            $adminNotif = $this->createNotification(
                $admin,
                NotificationType::COACH_APPLICATION,
                sprintf('New coach application from %s', $application->getUser()->getUsername()),
                $this->urlGenerator->generate('admin_coach_applications_show', ['id' => $application->getId()])
            );
            
            $this->log('✅ Admin notification created: ' . $adminNotif->getId());
            $this->publishNotification($adminNotif);
        }
        
        $this->log('🎉 All coach application notifications sent!');
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
            $this->log('⚠️ Mercure Hub not configured');
            return;
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

        try {
            $update = new Update(
                'notifications/user/' . $notification->getUser()->getId(),
                json_encode($data)
            );

            $this->hub->publish($update);
            $this->log('📡 Published to Mercure topic: notifications/user/' . $notification->getUser()->getId());
        } catch (\Exception $e) {
            $this->log('❌ Failed to publish to Mercure: ' . $e->getMessage());
        }
    }

    private function getAdmins(): array
    {
        $this->log('🔍 Searching for admins (including SUPER_ADMIN)...');
        
        try {
            // Get ALL users and filter by role in PHP
            // This respects the role hierarchy defined in security.yaml
            $allUsers = $this->entityManager->getRepository(User::class)->findAll();
            $this->log('📊 Total users found: ' . count($allUsers));
            
            $admins = [];
            foreach ($allUsers as $user) {
                $roles = $user->getRoles();
                $this->log('👤 Checking user: ' . $user->getUsername() . ' with roles: ' . implode(', ', $roles));
                
                // Check if user has ROLE_ADMIN or ROLE_SUPER_ADMIN
                if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true)) {
                    $admins[] = $user;
                    $this->log('✅ User ' . $user->getUsername() . ' is an admin!');
                }
            }
            
            $this->log('✅ Found ' . count($admins) . ' admin(s) total');
            
            return $admins;
        } catch (\Exception $e) {
            $this->log('❌ Error finding admins: ' . $e->getMessage());
            return [];
        }
    }
    
    private function log(string $message): void
    {
        if ($this->logger) {
            $this->logger->info($message);
        }
        
        // Also log to PHP error log for easy debugging
        error_log('[NotificationService] ' . $message);
    }
}