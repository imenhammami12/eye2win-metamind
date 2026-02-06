<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
class AdminController extends AbstractController
{
    /**
     * Route par défaut pour /admin - redirige vers le login ou le dashboard
     */
    #[Route('', name: 'admin_index')]
    public function index(): Response
    {
        // Si l'utilisateur est déjà connecté avec ROLE_ADMIN
        if ($this->isGranted('ROLE_ADMIN')) {
        return $this->redirectToRoute('admin_login');
        
        }
        
        // Sinon, rediriger vers le login admin
        return $this->redirectToRoute('admin_login');
    }
    
    #[Route('/dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
