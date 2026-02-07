<?php

namespace App\Controller\Admin;

use App\Entity\Message;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminMessageController extends AbstractController
{
    #[Route('/admin/message', name: 'admin_messages_index', methods: ['GET'])]
    public function index(MessageRepository $messageRepo, Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', 'active');

        $sort = (string) $request->query->get('sort', 'sentAt');
        $dir  = strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $messages = $messageRepo->findAdminList($q, $status, $sort, $dir);

        return $this->render('admin/message/index.html.twig', [
            'messages' => $messages,
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'dir' => $dir,
        ]);

    }

    #[Route('/admin/messages/{id}/toggle-delete', name: 'admin_messages_toggle_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleDelete(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_toggle_delete_'.$message->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('admin_messages_index');
        }

        $message->setIsDeleted(!$message->isDeleted());
        $em->flush();

        return $this->redirectToRoute('admin_messages_index');
    }


}
