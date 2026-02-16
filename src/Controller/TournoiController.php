<?php

namespace App\Controller;

use App\Entity\Tournoi;
use App\Repository\TournoiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/tournaments')]
#[IsGranted('ROLE_USER')]
class TournoiController extends AbstractController
{
    #[Route('/landing', name: 'app_tournoi_landing')]
    public function landing(): Response
    {
        return $this->render('tournoi/landing.html.twig');
    }

    #[Route('/', name: 'app_tournoi_index')]
    public function index(TournoiRepository $tournoiRepository): Response
    {
        return $this->render('tournoi/index.html.twig', [
            'tournaments' => $tournoiRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_tournoi_show')]
    public function show(Tournoi $tournoi): Response
    {
        return $this->render('tournoi/show.html.twig', [
            'tournament' => $tournoi,
            'matches' => $tournoi->getMatchs(),
        ]);
    }

    #[Route('/{id}/inscription', name: 'app_tournoi_inscription', methods: ['POST'])]
    public function inscription(Tournoi $tournoi, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($tournoi->getParticipants()->contains($user)) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit à ce tournoi.');
            return $this->redirectToRoute('app_tournoi_show', ['id' => $tournoi->getId()]);
        }

        $tournoi->addParticipant($user);
        $entityManager->flush();

        // Send confirmation email
        $email = (new TemplatedEmail())
            ->from(new Address('chaimaamri104@gmail.com', 'Eye2Win Support'))
            ->to($user->getEmail())
            ->subject('Confirmation d\'inscription au tournoi : ' . $tournoi->getNom())
            ->htmlTemplate('emails/registration.html.twig')
            ->context([
                'user' => $user,
                'tournoi' => $tournoi,
            ]);

        try {
            $mailer->send($email);
            $this->addFlash('success', 'Inscription réussie ! Un email de confirmation vous a été envoyé.');
        } catch (\Exception $e) {
            $this->addFlash('warning', 'Inscription réussie, mais l\'envoi de l\'email a échoué : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_tournoi_show', ['id' => $tournoi->getId()]);
    }
}
