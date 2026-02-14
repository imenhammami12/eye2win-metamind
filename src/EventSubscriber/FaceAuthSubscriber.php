<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class FaceAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $session = $this->requestStack->getSession();
        
        // Nettoyer toutes les variables de session liées à la reconnaissance faciale
        $session->remove('face_pre_login_verified');
        $session->remove('face_pre_login_verified_user_id');
        $session->remove('face_pre_login_verified_email');
        $session->remove('face_pre_login_user_id');
        $session->remove('face_pre_login_email');
    }
}