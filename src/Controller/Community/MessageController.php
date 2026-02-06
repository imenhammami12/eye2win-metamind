<?php

namespace App\Controller\Community;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractController
{
    /*#[Route('/community/message', name: 'app_message')]
    public function index(): Response
    {
        return $this->render('message/index.html.twig', [
            'controller_name' => 'MessageController',
        ]);
    }*/
    #[Route('/messages/{id}/edit', name: 'community_message_edit', requirements: ['id' => '\d+'])]
    public function edit(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        // Owner check
        if ($message->getSenderEmail() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException("Tu ne peux pas modifier ce message.");
        }

        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message->setEditedAt(new \DateTimeImmutable());
            $em->flush();

            return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
        }

        return $this->render('community/message/edit.html.twig', [
            'form' => $form,
            'message' => $message,
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
