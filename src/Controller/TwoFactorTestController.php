<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;

class TwoFactorTestController extends AbstractController
{
    #[Route('/test-2fa-status', name: 'test_2fa_status')]
    public function testStatus(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new Response('Not logged in', 401);
        }

        $data = [
            'email' => $user->getEmail(),
            'totp_secret' => $user->getTotpSecret(),
            'is_totp_enabled' => $user->getIsTotpEnabled(),
            'isTotpAuthenticationEnabled()' => $user->isTotpAuthenticationEnabled(),
            'implements_TwoFactorInterface' => $user instanceof \Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface,
            'token_class' => get_class($this->container->get('security.token_storage')->getToken()),
            'is_fully_authenticated' => $this->isGranted('IS_AUTHENTICATED_FULLY'),
            'is_2fa_in_progress' => $this->isGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS'),
        ];

        $html = '<h1>2FA Debug Info</h1><pre>' . print_r($data, true) . '</pre>';
        
        return new Response($html);
    }
}