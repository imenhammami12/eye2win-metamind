<?php

namespace App\Controller\Community;

use App\Entity\Message;
use App\Form\MessageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractController
{
    #[Route('/community/message', name: 'app_message')]
    public function index(): Response
    {
        return $this->render('message/index.html.twig', [
            'controller_name' => 'MessageController',
        ]);
    }

    #[Route('/messages/{id}/edit', name: 'community_message_edit', methods: ['GET','POST'], requirements: ['id' => '\d+'])]
    public function edit(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        if ($message->getSenderEmail() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException("Tu ne peux pas modifier ce message.");
        }

        if ($message->isDeleted()) {
            return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
        }

        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        // ✅ POST => save
        if ($request->isMethod('POST') && $form->isSubmitted() && $form->isValid()) {
            $message->setEditedAt(new \DateTimeImmutable());
            $em->flush();

            return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
        }

        // ✅ GET => retourne sur le channel avec ?edit=id (inline)
        return $this->redirectToRoute('community_channels_show', [
            'id' => $message->getChannel()->getId(),
            'edit' => $message->getId(),
        ]);
    }


    #[Route('/messages/{id}/delete', name: 'community_message_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        if ($message->getSenderEmail() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException("Tu ne peux pas supprimer ce message.");
        }

        if ($this->isCsrfTokenValid('delete_message_'.$message->getId(), $request->request->get('_token'))) {
            $message->setIsDeleted(true);
            $em->flush();
        }

        return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
    }

}
