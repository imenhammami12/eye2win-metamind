<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AdminSecurityController extends AbstractController
{
    /**
     * Point d'entrée unique du login admin.
     * - Si l'email est connu et que l'utilisateur a une face → redirige vers /admin/face-verify
     * - Sinon → formulaire classique email/password
     */
    #[Route('/admin/login', name: 'admin_login')]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        EntityManagerInterface $em
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Si un email est pré-renseigné, vérifie si cet utilisateur a la face activée
        $hasFaceUser = false;
        if ($lastUsername) {
            $user = $em->getRepository(User::class)->findOneBy(['email' => $lastUsername]);
            if ($user && $user->getFaceDescriptor()) {
                $hasFaceUser = true;
            }
        }

        return $this->render('admin/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
            'has_face_user' => $hasFaceUser,
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by the firewall logout key.');
    }
}