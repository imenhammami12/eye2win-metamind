<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class FaceAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        
        // Vérifier si l'utilisateur a la reconnaissance faciale activée
        if ($user instanceof User && $user->getFaceDescriptor() !== null) {
            $request = $event->getRequest();
            
            // Vérifier si la requête vient de la vérification faciale
            $route = $request->attributes->get('_route');
            $isFaceVerification = in_array($route, [
                'face_login_verify', 
                'face_verify_check'
            ]);
            
            if (!$isFaceVerification) {
                // Stocker l'utilisateur en session temporaire
                $session = $this->requestStack->getSession();
                $session->set('pending_face_verification', $user->getId());
                $session->set('pending_face_username', $user->getUsername());
                
                // Rediriger vers la vérification faciale
                $response = new RedirectResponse(
                    $this->urlGenerator->generate('face_verify_required')
                );
                $event->setResponse($response);
            }
        }
    }
}