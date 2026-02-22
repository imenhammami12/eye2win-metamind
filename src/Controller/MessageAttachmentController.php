<?php

namespace App\Controller;

use App\Entity\MessageAttachment;
use App\Repository\ChannelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


final class MessageAttachmentController extends AbstractController
{
    #[Route('/message/attachment', name: 'app_message_attachment')]
    public function index(): Response
    {
        return $this->render('message_attachment/index.html.twig', [
            'controller_name' => 'MessageAttachmentController',
        ]);
    }

    #[Route('/attachments/{id}/download', name: 'community_attachment_download', requirements: ['id'=>'\d+'])]
    public function download(MessageAttachment $att, ChannelRepository $channelRepo): Response
    {
        $msg = $att->getMessage();
        $channel = $msg->getChannel();

        // ✅ same visibility logic as show channel
        $visible = $channelRepo->findVisibleForUser();
        $visibleIds = array_map(fn($c) => $c->getId(), $visible);
        if (!in_array($channel->getId(), $visibleIds, true)) {
            throw $this->createAccessDeniedException();
        }

        if ($msg->isDeleted()) {
            throw $this->createNotFoundException();
        }

        $path = $this->getParameter('message_upload_dir').'/'.$att->getStoredName();

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $mime = $att->getMimeType() ?? 'application/octet-stream';
        $inline = str_starts_with($mime, 'image/')
            || $mime === 'application/pdf'
            || str_starts_with($mime, 'text/');

        $disposition = $inline
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        return $this->file($path, $att->getOriginalName(), $disposition);

        /*$path = $this->getParameter('message_upload_dir').'/'.$att->getStoredName();
        return $this->file($path, $att->getOriginalName());*/
    }

}
