<?php

namespace App\Twig\Extension;

use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class NotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private NotificationRepository $notificationRepo,
        private Security               $security
    )
    {
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user) {
            return [
                'navNotifications' => [],
                'navUnreadCount' => 0,
            ];
        }

        $notifs = $this->notificationRepo->findChannelNotificationsForUser($user,10);
        // unread count
        $unread = 0;
        foreach ($notifs as $n) {
            if (!$n->isRead()) $unread++;
        }

        return [
            'navNotifications' => $notifs,
            'navUnreadCount' => $unread,
        ];
    }

}
