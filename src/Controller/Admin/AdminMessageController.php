<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminMessageController extends AbstractController
{
    #[Route('/admin/message', name: 'admin_messages_index')]
    public function index(): Response
    {
        return $this->render('admin/message/index.html.twig', [
            'controller_name' => 'Admin/AdminMessageController',
        ]);
    }
}
