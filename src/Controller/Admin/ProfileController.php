<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/profile')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        return $this->render('admin/profile/index.html.twig', [
            'user' => $user,
            'hasFaceAuth' => $user->getFaceDescriptor() !== null
        ]);
    }

    #[Route('/security', name: 'app_profile_security')]
    public function security(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        return $this->render('admin/profile/security.html.twig', [
            'user' => $user,
            'hasFaceAuth' => $user->getFaceDescriptor() !== null,
            'has2FA' => $user->isTotpAuthenticationEnabled(),
            'backupCodesCount' => $user->getRemainingBackupCodesCount()
        ]);
    }

    /**
     * Page dédiée d'enregistrement facial
     */
    #[Route('/face-register', name: 'app_profile_face_register')]
    public function faceRegisterPage(): Response
    {
        return $this->render('admin/profile/face_register.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}