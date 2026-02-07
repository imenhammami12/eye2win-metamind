<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Psr\Log\LoggerInterface;

class TwoFactorDebugSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $token = $event->getAuthenticatedToken();
        
        $this->logger->info('🔐 LOGIN SUCCESS EVENT TRIGGERED', [
            'user' => $user->getUserIdentifier(),
            'token_class' => get_class($token),
            'is_2fa_user' => method_exists($user, 'isTotpAuthenticationEnabled') ? $user->isTotpAuthenticationEnabled() : 'N/A',
        ]);
    }
}