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
//real time chat
use App\Entity\Channel;
use App\Repository\ChannelRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

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
            throw $this->createAccessDeniedException("you can't edit this message.");
        }

        if ($message->isDeleted()) {
            return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
        }

        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        // POST => save
        if ($request->isMethod('POST') && $form->isSubmitted() && $form->isValid()) {
            $message->setEditedAt(new \DateTimeImmutable());
            $em->flush();

            return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
        }

        // GET => retourne sur le channel avec ?edit=id (inline)
        return $this->redirectToRoute('community_channels_show', [
            'id' => $message->getChannel()->getId(),
            'edit' => $message->getId(),
        ]);
    }


    #[Route('/messages/{id}/delete', name: 'community_message_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Message $message, Request $request, EntityManagerInterface $em): Response
    {
        if ($message->getSenderEmail() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException("you can't delete message.");
        }

        if ($this->isCsrfTokenValid('delete_message_'.$message->getId(), $request->request->get('_token'))) {
            $message->setIsDeleted(true);
            $em->flush();
        }

        return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
    }

    #[Route('/channels/{id}/messages', name: 'community_message_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function send(
        Channel $channel,
        Request $request,
        EntityManagerInterface $em,
        ChannelRepository $channelRepo,
        HubInterface $hub,
        Environment $twig
    ): Response {
        // ✅ same visibility check as ChannelController::show()
        $visible = $channelRepo->findVisibleForUser();
        $visibleIds = array_map(fn($c) => $c->getId(), $visible);
        if (!in_array($channel->getId(), $visibleIds, true)) {
            throw $this->createAccessDeniedException("Channel non accessible.");
        }

        $message = new Message();
        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false], 422);
            }
            return $this->redirectToRoute('community_channels_show', ['id' => $channel->getId()]);
        }

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

        /*/ ✅ Render HTML for "other users" (no actions/CSRF)
        $htmlForOthers = $twig->render('community/message/_row.html.twig', [
            'm' => $message,
            'channel' => $channel,
            'isMe' => false,
            'showActions' => false,
        ]);*/

        /*/ ✅ Publish to Mercure on a per-channel topic
        $topic = 'urn:channel:'.$channel->getId();

        $update = new Update($topic, json_encode(['id' => $message->getId(), 'html' => $htmlForOthers]));

        $hub->publish($update);*/
        // ✅ Publish to Mercure on a per-channel topic (JSON payload)
        $topic = 'urn:channel:'.$channel->getId();

        $payload = [
            'id' => $message->getId(),
            'senderEmail' => $message->getSenderEmail(),
            'senderName' => $message->getSenderName(),
            'content' => $message->getContent(),
            'sentAt' => $message->getSentAt()->format('d/m/Y H:i'),
            'isDeleted' => $message->isDeleted(),
        ];

        $hub->publish(new Update($topic, json_encode($payload)));


        // ✅ Sender gets HTML with actions (CSRF valid for THIS session)
        if ($request->isXmlHttpRequest()) {
            $htmlForSender = $twig->render('community/message/_row.html.twig', [
                'm' => $message,
                'channel' => $channel,
                'isMe' => true,
                'showActions' => true,
            ]);

            return new JsonResponse(['ok' => true, 'id' => $message->getId(), 'html' => $htmlForSender]);
        }

        return $this->redirectToRoute('community_channels_show', ['id' => $channel->getId()]);
    }


}
