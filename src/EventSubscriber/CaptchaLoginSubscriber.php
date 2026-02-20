<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class CaptchaLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Cibler uniquement POST /login (front office)
        if ($request->getPathInfo() !== '/login' || !$request->isMethod('POST')) {
            return;
        }

        $token = $request->request->get('gaming_captcha_token', '');

        // Token absent
        if (empty($token)) {
            $this->redirectWithError($event, 'captcha_missing');
            return;
        }

        // Valider le format du token : GC_{timestamp}_{score}_ok
        // Exemple : GC_1771603960094_2_ok
        if (!$this->isValidToken($token)) {
            $this->redirectWithError($event, 'captcha_invalid');
        }
    }

    private function isValidToken(string $token): bool
    {
        // Format attendu : GC_TIMESTAMP_SCORE_ok
        if (!preg_match('/^GC_(\d+)_([23])_ok$/', $token, $matches)) {
            return false;
        }

        $timestamp = (int) $matches[1];
        $now       = (int) (microtime(true) * 1000);

        // Token valide seulement 10 minutes (600 000 ms)
        if (($now - $timestamp) > 600000) {
            return false;
        }

        return true;
    }

    private function redirectWithError(RequestEvent $event, string $error): void
    {
        $url = $this->router->generate('app_login') . '?captcha_error=' . $error;
        $event->setResponse(new RedirectResponse($url));
    }
}