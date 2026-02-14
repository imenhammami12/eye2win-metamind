<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

class AdminGateController extends AbstractController
{
    public function __construct(private RequestStack $requestStack) {}

    #[Route('/admin/gate', name: 'admin_gate')]
    public function gate(EntityManagerInterface $em): Response
    {
        $session = $this->requestStack->getSession();
        
        // ══════════════════════════════════════════════════════════
        // 1. Déjà connecté avec ROLE_ADMIN → Dashboard directement
        // ══════════════════════════════════════════════════════════
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $currentUser = $this->getUser();

        // ══════════════════════════════════════════════════════════
        // 2. Utilisateur connecté (ROLE_USER mais pas ROLE_ADMIN)
        // ══════════════════════════════════════════════════════════
        if ($currentUser instanceof User) {
            
            // Vérifier si cet utilisateur a les droits admin dans ses rôles
            if (!in_array('ROLE_ADMIN', $currentUser->getRoles())) {
                $this->addFlash('error', 'Accès refusé : vous n\'avez pas les droits administrateur.');
                return $this->redirectToRoute('app_home');
            }

            // ── CAS A : Utilisateur admin avec reconnaissance faciale activée ─────
            if ($currentUser->getFaceDescriptor()) {
                
                // Vérifier si la face a déjà été vérifiée dans cette session
                $faceVerified = $session->get('face_pre_login_verified') === true;
                $verifiedUserId = (int)$session->get('face_pre_login_verified_user_id');
                
                if ($faceVerified && $verifiedUserId === (int)$currentUser->getId()) {
                    // Face déjà vérifiée → aller au login pour saisir le mot de passe
                    return $this->redirectToRoute('admin_login');
                }
                
                // Face pas encore vérifiée → rediriger vers la page de reconnaissance faciale
                $session->set('face_pre_login_email', $currentUser->getEmail());
                $session->set('face_pre_login_verified_email', $currentUser->getEmail());
                $session->set('face_pre_login_user_id', (int)$currentUser->getId());
                
                return $this->redirectToRoute('face_pre_login_page');
            }
            
            // ── CAS B : Utilisateur admin SANS reconnaissance faciale ─────────────
            // Aller directement au login classique
            return $this->redirectToRoute('admin_login');
        }

        // ══════════════════════════════════════════════════════════
        // 3. Utilisateur NON connecté
        // ══════════════════════════════════════════════════════════
        // Chercher s'il existe au moins un admin avec face activée
        $adminWithFace = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.faceDescriptor IS NOT NULL')
            ->andWhere('u.rolesJson LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($adminWithFace) {
            // Au moins un admin a la face activée → rediriger vers la page de reconnaissance
            return $this->redirectToRoute('face_pre_login_page');
        }

        // Aucun admin avec face → login classique directement
        return $this->redirectToRoute('admin_login');
    }
}