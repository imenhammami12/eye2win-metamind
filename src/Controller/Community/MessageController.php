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

/// FILE ATTACHMENTS
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\ByteString;
use App\Entity\MessageAttachment;



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
    public function edit(Message $message, Request $request, EntityManagerInterface $em, HubInterface $hub): Response
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

            $topic = 'urn:channel:'.$message->getChannel()->getId();

            $hub->publish(new Update($topic, json_encode([
                'type' => 'edit',
                'id' => $message->getId(),
                'senderEmail' => $message->getSenderEmail(),
                'content' => $message->getContent(),
                'editedAt' => $message->getEditedAt()->format('d/m/Y H:i'),
            ])));

            // ✅ if you want no refresh edit (AJAX)
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'ok' => true,
                    'id' => $message->getId(),
                    'content' => $message->getContent(),
                    'editedAt' => $message->getEditedAt()->format('d/m/Y H:i'),
                ]);
            }

            return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
        }

        // GET => retourne sur le channel avec ?edit=id (inline)
        return $this->redirectToRoute('community_channels_show', [
            'id' => $message->getChannel()->getId(),
            'edit' => $message->getId(),
        ]);
    }

    #[Route('/messages/{id}/delete', name: 'community_message_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Message $message, Request $request, EntityManagerInterface $em, HubInterface $hub): Response
    {
        if ($message->getSenderEmail() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException("you can't delete message.");
        }

        if (!$this->isCsrfTokenValid('delete_message_'.$message->getId(), $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false], 403);
            }
            return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
        }

        $message->setIsDeleted(true);
        $message->setEditedAt(new \DateTimeImmutable());
        $em->flush();

        $topic = 'urn:channel:'.$message->getChannel()->getId();

        $hub->publish(new Update($topic, json_encode([
            'type' => 'delete',
            'id' => $message->getId(),
            'senderEmail' => $message->getSenderEmail(),
        ])));

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'id' => $message->getId()]);
        }

        return $this->redirectToRoute('community_channels_show', ['id' => $message->getChannel()->getId()]);
    }


//    #[Route('/channels/{id}/messages', name: 'community_message_send', methods: ['POST'], requirements: ['id' => '\d+'])]
//    public function send(
//        Channel $channel,
//        Request $request,
//        EntityManagerInterface $em,
//        ChannelRepository $channelRepo,
//        HubInterface $hub,
//        Environment $twig
//    ): Response {
//        // ✅ same visibility check as ChannelController::show()
//        $visible = $channelRepo->findVisibleForUser();
//        $visibleIds = array_map(fn($c) => $c->getId(), $visible);
//        if (!in_array($channel->getId(), $visibleIds, true)) {
//            throw $this->createAccessDeniedException("Channel non accessible.");
//        }
//
//        $message = new Message();
//        $form = $this->createForm(MessageType::class, $message);
//        $form->handleRequest($request);
//
//        if (!$form->isSubmitted() || !$form->isValid()) {
//            if ($request->isXmlHttpRequest()) {
//                return new JsonResponse(['ok' => false], 422);
//            }
//            return $this->redirectToRoute('community_channels_show', ['id' => $channel->getId()]);
//        }
//
//        $now = new \DateTimeImmutable();
//        $user = $this->getUser();
//
//        $message->setChannel($channel);
//        $message->setSentAt($now);
//        $message->setEditedAt($now);
//        $message->setIsDeleted(false);
//        $message->setSenderName(method_exists($user, 'getUsername') ? $user->getUsername() : $user->getUserIdentifier());
//        $message->setSenderEmail($user->getUserIdentifier());
//
//        $em->persist($message);
//        $em->flush();
//
//        $topic = 'urn:channel:'.$channel->getId();
//
//        $payload = [
//            'type' => 'new',
//            'id' => $message->getId(),
//            'senderEmail' => $message->getSenderEmail(),
//            'senderName' => $message->getSenderName(),
//            'content' => $message->getContent(),
//            'sentAt' => $message->getSentAt()->format('d/m/Y H:i'),
//            //'editedAt' => null,
//            'isDeleted' => $message->isDeleted(),
//            'attachments' => array_map(fn($a) => [
//                'id' => $a->getId(),
//                'name' => $a->getOriginalName(),
//                'url' => $this->generateUrl('community_attachment_download', ['id' => $a->getId()]),
//                'mime' => $a->getMimeType(),
//            ], $message->getAttachments()->toArray()),
//        ];
//
//        $hub->publish(new Update($topic, json_encode($payload)));
//
//
//        // ✅ Sender gets HTML with actions (CSRF valid for THIS session)
//        if ($request->isXmlHttpRequest()) {
//            $htmlForSender = $twig->render('community/message/_row.html.twig', [
//                'm' => $message,
//                'channel' => $channel,
//                'isMe' => true,
//                'showActions' => true,
//            ]);
//
//            return new JsonResponse(['ok' => true, 'id' => $message->getId(), 'html' => $htmlForSender]);
//        }
//
//        $files = $form->get('files')->getData(); // array of UploadedFile
//
//// ✅ allow message with only files
//        if (trim((string)$message->getContent()) === '' && empty($files)) {
//            if ($request->isXmlHttpRequest()) return new JsonResponse(['ok'=>false,'error'=>'Empty message'], 422);
//            return $this->redirectToRoute('community_channels_show', ['id' => $channel->getId()]);
//        }
//
//        $uploadDir = $this->getParameter('message_upload_dir');
//
//        foreach ($files as $file) {
//            /** @var UploadedFile $file */
//            $stored = ByteString::fromRandom(32)->toString().'.'.$file->guessExtension();
//
//            $file->move($uploadDir, $stored);
//
//            $att = new MessageAttachment();
//            $att->setOriginalName($file->getClientOriginalName());
//            $att->setStoredName($stored);
//            $att->setMimeType($file->getClientMimeType() ?? 'application/octet-stream');
//            $att->setSize($file->getSize() ?: 0);
//
//            $message->addAttachment($att);
//        }
//        return $this->redirectToRoute('community_channels_show', ['id' => $channel->getId()]);
//
//    }
    #[Route('/channels/{id}/messages', name: 'community_message_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function send(
        Channel $channel,
        Request $request,
        EntityManagerInterface $em,
        ChannelRepository $channelRepo,
        HubInterface $hub,
        Environment $twig
    ): Response {

        // ✅ visibility check
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
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                return new JsonResponse(['ok' => false, 'errors' => $errors], 422);
            }
            return $this->redirectToRoute('community_channels_show', ['id' => $channel->getId()]);
        }

        /** @var UploadedFile[] $files */
        $files = $form->get('files')->getData() ?? [];

        // ✅ allow message with only files
        if (trim((string) $message->getContent()) === '' && count($files) === 0) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'Empty message'], 422);
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
        // ✅ ensure DB won't get NULL (important for "files-only" messages)
        $message->setContent((string) $message->getContent());

        $em->persist($message);

        // ✅ save files BEFORE flush (same transaction)
        $uploadDir = $this->getParameter('message_upload_dir');

// Ensure the directory exists (var/uploads/messages)
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            // Best effort extension (guessExtension can be null)
            $ext = $file->guessExtension()
                ?: pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION)
                    ?: 'bin';

            $stored = ByteString::fromRandom(32)->toString() . '.' . $ext;
            $originalName = (string) $file->getClientOriginalName();
            $mime = $file->getClientMimeType() ?? 'application/octet-stream';
            $size = $file->getSize() ?? 0;

            // Move file to storage
            try {
                $file->move($uploadDir, $stored);
            } catch (\Throwable $e) {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'ok' => false,
                        'error' => 'Upload failed: '.$e->getMessage(),
                    ], 500);
                }
                throw $e;
            }

            $att = new MessageAttachment();
            $att->setOriginalName($originalName);
            $att->setStoredName($stored);
            $att->setMimeType($mime);
            $att->setSize($size);

            // ✅ THIS is the important part (updates both sides)
            $message->addAttachment($att);

            // Persist explicitly (safe even if cascade persist exists)
            $em->persist($att);
        }

        // ✅ ONE flush at the end (message + attachments get IDs)
        $em->flush();

        // ✅ build payload AFTER flush (so attachment IDs exist)
        $topic = 'urn:channel:' . $channel->getId();

        $payload = [
            'type' => 'new',
            'id' => $message->getId(),
            'senderEmail' => $message->getSenderEmail(),
            'senderName' => $message->getSenderName(),
            'content' => $message->getContent(),
            'sentAt' => $message->getSentAt()->format('d/m/Y H:i'),
            'isDeleted' => $message->isDeleted(),
            'attachments' => array_map(fn($a) => [
                'id' => $a->getId(),
                'name' => $a->getOriginalName(),
                'url' => $this->generateUrl('community_attachment_download', ['id' => $a->getId()]),
                'mime' => $a->getMimeType(),
                'size' => $a->getSize(),
            ], $message->getAttachments()->toArray()),
        ];

        $hub->publish(new Update($topic, json_encode($payload)));

        // ✅ AJAX response AFTER files are saved
        if ($request->isXmlHttpRequest()) {
            $htmlForSender = $twig->render('community/message/_row.html.twig', [
                'm' => $message,
                'channel' => $channel,
                'isMe' => true,
                'showActions' => true,
            ]);

            return new JsonResponse([
                'ok' => true,
                'id' => $message->getId(),
                'html' => $htmlForSender
            ]);
        }

        return $this->redirectToRoute('community_channels_show', ['id' => $channel->getId()]);
    }


}
