<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DisableCsrfForFaceRoutesListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Liste des routes où le CSRF doit être désactivé
        $facePaths = [
            '/face-pre-login-verify',
            '/face-pre-check',
            '/face-login/verify',
            '/face/register',
            '/face/remove',
        ];
        
        $path = $request->getPathInfo();
        
        foreach ($facePaths as $facePath) {
            if (str_starts_with($path, $facePath)) {
                // Désactiver la vérification CSRF pour cette requête
                $request->attributes->set('_disable_csrf_protection', true);
                break;
            }
        }
    }
}
