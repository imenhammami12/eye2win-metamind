<?php

namespace App\Controller\Community;

use App\Entity\Channel;
use App\Entity\Message;
use App\Form\ChannelType;
use App\Form\MessageType;
use App\Repository\ChannelRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ChannelController extends AbstractController
{
    #[Route('/channels', name: 'community_channels_index')]
    public function index(ChannelRepository $repo): Response
    {
        $channels = $repo->findVisibleForUser();

        return $this->render('community/channel/index.html.twig', [
            'channels' => $channels,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/channels/new', name: 'community_channels_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $channel = new Channel();
        $form = $this->createForm(ChannelType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();

            $channel->setStatus(Channel::STATUS_PENDING);
            $channel->setIsActive(false);
            $channel->setCreatedAt($now);
            $channel->setCreatedBy($this->getUser()->getUserIdentifier());

            $em->persist($channel);
            $em->flush();

            $this->addFlash('success', 'Channel créé ✅ En attente de validation admin.');
            return $this->redirectToRoute('community_channels_index');
        }

        return $this->render('community/channel/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/channels/{id}', name: 'community_channels_show', requirements: ['id' => '\d+'])]
    public function show(
        Channel $channel,
        MessageRepository $messageRepo,
        Request $request,
        EntityManagerInterface $em,
        ChannelRepository $channelRepo
    ): Response
    {
        // ✅ Access control: only APPROVED+active+allowed (same logic as index)
        $visible = $channelRepo->findVisibleForUser();
        $visibleIds = array_map(fn($c) => $c->getId(), $visible);

        if (!in_array($channel->getId(), $visibleIds, true)) {
            throw $this->createAccessDeniedException("Channel non accessible.");
        }

        $messages = $messageRepo->findForChannelVisible($channel->getId());

        // Message form only if logged in
        $message = new Message();
        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if ($this->isGranted('ROLE_USER') && $form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();
            $user = $this->getUser();

            $message->setChannel($channel);
            $message->setSentAt($now);
            $message->setEditedAt($now);
            $message->setIsDeleted(false);
            $message->setSenderName(method_exists($user, 'getUsername') ? $user->getUsername() : $user->getUserIdentifier());
            $message->setSenderEmail($user->getUserIdentifier());

            $em->persist($message);
            $em->flush();

            return $this->redirectToRoute('community_channels_show', ['id' => $channel->getId()]);
        }

        return $this->render('community/channel/show.html.twig', [
            'channel' => $channel,
            'messages' => $messages,
            'messageForm' => $form,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/channels/{id}/edit', name: 'community_channels_edit', requirements: ['id' => '\d+'])]
    public function edit(Channel $channel, Request $request, EntityManagerInterface $em): Response
    {
        // ✅ only creator can edit
        $identifier = $this->getUser()?->getUserIdentifier();
        if ($channel->getCreatedBy() !== $identifier) {
            throw $this->createAccessDeniedException("Vous ne pouvez pas modifier ce channel.");
        }

        $form = $this->createForm(ChannelType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Channel modifié ✅');
            return $this->redirectToRoute('community_channels_index');
        }

        return $this->render('community/channel/edit.html.twig', [
            'channel' => $channel,
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/channels/{id}/delete', name: 'community_channels_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Channel $channel, Request $request, EntityManagerInterface $em): Response
    {
        // ✅ only creator can delete
        $identifier = $this->getUser()?->getUserIdentifier();
        if ($channel->getCreatedBy() !== $identifier) {
            throw $this->createAccessDeniedException("Vous ne pouvez pas supprimer ce channel.");
        }

        if (!$this->isCsrfTokenValid('delete_channel_'.$channel->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('community_channels_index');
        }

        $em->remove($channel);
        $em->flush();

        $this->addFlash('success', 'Channel supprimé ✅');
        return $this->redirectToRoute('community_channels_index');
    }


}
