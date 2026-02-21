<?php

namespace App\Controller;

use App\Entity\LiveAccess;
use App\Entity\LiveStream;
use App\Entity\User;
use App\Form\LiveStreamType;
use App\Repository\LiveStreamRepository;
use App\Repository\LiveAccessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/live')]
class LiveStreamController extends AbstractController
{
    #[Route('/', name: 'live_index')]
    public function index(LiveStreamRepository $liveRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $lives = $liveRepo->findScheduledAndLive();

        return $this->render('live/index.html.twig', [
            'lives' => $lives,
            'user' => $user,
            'isCoach' => in_array('ROLE_COACH', $user->getRoles()),
        ]);
    }

    #[Route('/create', name: 'live_create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        /** @var User $user */
        $user = $this->getUser();

        $live = new LiveStream();
        $live->setCoach($user);

        $form = $this->createForm(LiveStreamType::class, $live);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($live);
            $em->flush();

            $this->addFlash('success', 'Live stream created! You can now start streaming.');
            return $this->redirectToRoute('live_manage', ['id' => $live->getId()]);
        }

        return $this->render('live/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/manage/{id}', name: 'live_manage')]
    public function manage(LiveStream $live): Response
    {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        /** @var User $user */
        $user = $this->getUser();

        if ($live->getCoach() !== $user) {
            throw $this->createAccessDeniedException('You cannot manage this live stream.');
        }

        return $this->render('live/manage.html.twig', [
            'live' => $live,
        ]);
    }

    #[Route('/start/{id}', name: 'live_start', methods: ['POST'])]
    public function start(LiveStream $live, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        /** @var User $user */
        $user = $this->getUser();

        if ($live->getCoach() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('live_start_' . $live->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $live->setStatus('live');
        $live->setStartedAt(new \DateTime());
        $em->flush();

        $this->addFlash('success', 'Your live stream is now LIVE!');
        return $this->redirectToRoute('live_manage', ['id' => $live->getId()]);
    }

    #[Route('/end/{id}', name: 'live_end', methods: ['POST'])]
    public function end(LiveStream $live, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        /** @var User $user */
        $user = $this->getUser();

        if ($live->getCoach() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('live_end_' . $live->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $live->setStatus('ended');
        $live->setEndedAt(new \DateTime());
        $em->flush();

        $this->addFlash('info', 'Your live stream has ended.');
        return $this->redirectToRoute('live_index');
    }

    #[Route('/watch/{id}', name: 'live_watch')]
    public function watch(
        LiveStream $live,
        Request $request,
        EntityManagerInterface $em,
        LiveAccessRepository $accessRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        // Coach who owns this live can always watch for free
        if ($live->getCoach() === $user) {
            return $this->render('live/watch.html.twig', [
                'live' => $live,
                'hasAccess' => true,
                'isOwner' => true,
            ]);
        }

        // Check if live is accessible
        if ($live->isEnded()) {
            $this->addFlash('info', 'This live stream has ended.');
            return $this->redirectToRoute('live_index');
        }

        // Check existing access
        $access = $accessRepo->findOneBy(['user' => $user, 'liveStream' => $live]);
        $hasAccess = $access !== null;

        // Handle coin payment
        if (!$hasAccess && $request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('live_join_' . $live->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $coinPrice = $live->getCoinPrice();
            $userCoins = $user->getCoinBalance();

            if ($userCoins < $coinPrice) {
                $this->addFlash('error', 'You do not have enough EyeTwin Coins. Please purchase more coins.');
                return $this->redirectToRoute('live_watch', ['id' => $live->getId()]);
            }

            // Deduct coins
            $user->setCoinBalance($userCoins - $coinPrice);

            // Create access record
            $liveAccess = new LiveAccess();
            $liveAccess->setUser($user);
            $liveAccess->setLiveStream($live);
            $liveAccess->setCoinsSpent($coinPrice);
            $em->persist($liveAccess);
            $em->flush();

            $hasAccess = true;
            $this->addFlash('success', 'Access granted! Enjoy the live stream.');
        }

        return $this->render('live/watch.html.twig', [
            'live' => $live,
            'hasAccess' => $hasAccess,
            'isOwner' => false,
        ]);
    }

    #[Route('/my-streams', name: 'live_my_streams')]
    public function myStreams(LiveStreamRepository $liveRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        /** @var User $user */
        $user = $this->getUser();

        $streams = $liveRepo->findBy(['coach' => $user], ['createdAt' => 'DESC']);

        return $this->render('live/my_streams.html.twig', [
            'streams' => $streams,
        ]);
    }
}
