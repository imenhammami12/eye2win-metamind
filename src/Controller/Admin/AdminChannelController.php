<?php

namespace App\Controller\Admin;

use App\Entity\Channel;
use App\Form\ChannelType;
use App\Repository\ChannelRepository;
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
    public function index(ChannelRepository $repo): Response
    {
        return $this->render('admin/channel/index.html.twig', [
            'channels' => $repo->findAllForAdmin(),
        ]);
    }

    #[Route('/pending', name: 'admin_channels_pending')]
    public function pending(ChannelRepository $repo): Response
    {
        return $this->render('admin/channel/pending.html.twig', [
            'channels' => $repo->findPending(),
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_channels_approve', methods: ['POST'])]
    public function approve(Channel $channel, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('approve_'.$channel->getId(), $request->request->get('_token'))) {
            $channel->setStatus(Channel::STATUS_APPROVED);
            $channel->setIsActive(true);
            $channel->setApprovedBy($this->getUser()->getUserIdentifier());
            $channel->setApprovedAt(new \DateTimeImmutable());
            $channel->setRejectionReason(null);
            $em->flush();
        }

        return $this->redirectToRoute('admin_channels_pending');
    }

    #[Route('/{id}/reject', name: 'admin_channels_reject', methods: ['POST'])]
    public function reject(Channel $channel, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('reject_'.$channel->getId(), $request->request->get('_token'))) {
            $reason = $request->request->get('reason');
            $channel->setStatus(Channel::STATUS_REJECTED);
            $channel->setIsActive(false);
            $channel->setApprovedBy($this->getUser()->getUserIdentifier());
            $channel->setApprovedAt(new \DateTimeImmutable());
            $channel->setRejectionReason($reason ?: 'Rejected by admin');
            $em->flush();
        }

        return $this->redirectToRoute('admin_channels_pending');
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

            // ✅ Admin creates directly approved + active
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
            $this->addFlash('success', 'Channel modifié ✅');
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

        $this->addFlash('success', 'Channel supprimé ✅');
        return $this->redirectToRoute('admin_channels_index');
    }

}
