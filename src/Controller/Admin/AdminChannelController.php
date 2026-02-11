<?php

namespace App\Controller\Admin;

use App\Entity\Channel;
use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Form\ChannelType;
use App\Repository\ChannelRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/channels')]
#[IsGranted('ROLE_ADMIN')]
class AdminChannelController extends AbstractController
{
    #[Route('/', name: 'admin_channels_index')]
    public function index(ChannelRepository $repo, Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', 'all'); // all|approved|pending|rejected
        $type = (string) $request->query->get('type', 'all');     // all|public|private (enum)
        $active = (string) $request->query->get('active', 'all'); // all|1|0

        // ✅ sorting
        $sort = (string) $request->query->get('sort', 'createdAt');
        $dir  = strtolower((string) $request->query->get('dir', 'desc'));
        $dir  = $dir === 'asc' ? 'asc' : 'desc';

        $channels = $repo->findAdminList($q, $status, $type, $active,$sort,$dir);

        // stats pour cards
        $pendingCount = $repo->countByStatus(Channel::STATUS_PENDING);

        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/channel/_table.html.twig', [
                'channels' => $channels,
                'q' => $q,
                'status' => $status,
                'type' => $type,
                'active' => $active,
                'sort' => $sort,
                'dir' => $dir,
                'pendingCount' => $pendingCount,
            ]);
        }


        return $this->render('admin/channel/index.html.twig', [
            'channels' => $channels,
            'q' => $q,
            'status' => $status,
            'type' => $type,
            'active' => $active,
            'pendingCount' => $pendingCount,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_channels_approve', methods: ['POST'])]
    public function approve(Channel $channel, Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($this->isCsrfTokenValid('approve_'.$channel->getId(), $request->request->get('_token'))) {
            $channel->setStatus(Channel::STATUS_APPROVED);
            $channel->setIsActive(true);
            $channel->setApprovedBy($this->getUser()->getUserIdentifier());
            $channel->setApprovedAt(new \DateTimeImmutable());
            $channel->setRejectionReason(null);
            $em->flush();

            $creator = $userRepo->findOneBy(['email' => $channel->getCreatedBy()]);
            if ($creator) {
                $notification = new Notification();
                $notification->setUser($creator);
                $notification->setType(NotificationType::CHANNEL_APPROVED);
                $notification->setMessage(sprintf('Your channel "%s" has been approved by an admin. It is now visible in the community.', $channel->getName()));
                $notification->setLink($this->generateUrl('community_channels_show', ['id' => $channel->getId()]));
                $em->persist($notification);
                $em->flush();
            }
        }

        return $this->redirectToRoute('admin_channels_index');
    }

    #[Route('/{id}/reject', name: 'admin_channels_reject', methods: ['POST'])]
    public function reject(Channel $channel, Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($this->isCsrfTokenValid('reject_'.$channel->getId(), $request->request->get('_token'))) {
            $reason = $request->request->get('reason');
            $channel->setStatus(Channel::STATUS_REJECTED);
            $channel->setIsActive(false);
            $channel->setApprovedBy($this->getUser()->getUserIdentifier());
            $channel->setApprovedAt(new \DateTimeImmutable());
            $channel->setRejectionReason($reason ?: 'Rejected by admin');
            $em->flush();

            $creator = $userRepo->findOneBy(['email' => $channel->getCreatedBy()]);
            if ($creator) {
                $notification = new Notification();
                $notification->setUser($creator);
                $notification->setType(NotificationType::CHANNEL_REJECTED);
                $message = sprintf('Your channel "%s" has been rejected by an admin.', $channel->getName());
                if ($reason) {
                    $message .= ' Reason: ' . $reason;
                }
                $notification->setMessage($message);
                $notification->setLink($this->generateUrl('community_channels_index'));
                $em->persist($notification);
                $em->flush();
            }
        }

        return $this->redirectToRoute('admin_channels_index');
    }

    #[Route('/{id}/toggle-active', name: 'admin_channels_toggle', methods: ['POST'])]
    public function toggle(Channel $channel, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('toggle_'.$channel->getId(), $request->request->get('_token'))) {
            $channel->setIsActive(!$channel->isActive());
            $em->flush();
        }

        return $this->redirectToRoute('admin_channels_index');
    }

    #[Route('/new', name: 'admin_channels_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $channel = new Channel();
        $form = $this->createForm(ChannelType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();

            $channel->setCreatedAt($now);
            $channel->setCreatedBy($this->getUser()->getUserIdentifier());

            // Admin creates directly approved + active
            $channel->setStatus(Channel::STATUS_APPROVED);
            $channel->setIsActive(true);
            $channel->setApprovedBy($this->getUser()->getUserIdentifier());
            $channel->setApprovedAt($now);
            $channel->setRejectionReason(null);

            $em->persist($channel);
            $em->flush();

            $this->addFlash('success', 'Channel créé ✅');
            return $this->redirectToRoute('admin_channels_index');
        }

        return $this->render('admin/channel/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_channels_edit', requirements: ['id' => '\d+'])]
    public function edit(Channel $channel, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ChannelType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Channel edited ✅');
            return $this->redirectToRoute('admin_channels_index');
        }

        return $this->render('admin/channel/edit.html.twig', [
            'channel' => $channel,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_channels_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Channel $channel, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_delete_channel_'.$channel->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('admin_channels_index');
        }

        $em->remove($channel);
        $em->flush();

        $this->addFlash('success', 'Channel deleted ✅');
        return $this->redirectToRoute('admin_channels_index');
    }

}
