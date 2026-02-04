<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\CoachApplication;
use App\Entity\ApplicationStatus;
use App\Form\UserProfileType;
use App\Form\CoachApplicationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/profile')]
class UserController extends AbstractController
{
    #[Route('/', name: 'user_profile')]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        /** @var User $user */
        $user = $this->getUser();
        
        // Récupérer la dernière demande de coach
        $latestCoachApplication = null;
        if (!$user->getCoachApplications()->isEmpty()) {
            $applications = $user->getCoachApplications()->toArray();
            usort($applications, fn($a, $b) => $b->getSubmittedAt() <=> $a->getSubmittedAt());
            $latestCoachApplication = $applications[0];
        }
        
        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'latestCoachApplication' => $latestCoachApplication,
        ]);
    }

    #[Route('/edit', name: 'user_edit_profile')]
    public function editProfile(
        Request $request, 
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de la photo de profil
            $profilePictureFile = $form->get('profilePictureFile')->getData();
            
            if ($profilePictureFile) {
                $originalFilename = pathinfo($profilePictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$profilePictureFile->guessExtension();

                try {
                    $profilePictureFile->move(
                        $this->getParameter('profile_pictures_directory'),
                        $newFilename
                    );
                    $user->setProfilePicture($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            $em->flush();
            
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès !');
            return $this->redirectToRoute('user_profile');
        }

        return $this->render('user/edit_profile.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/statistics', name: 'user_statistics')]
    public function statistics(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        /** @var User $user */
        $user = $this->getUser();
        
        // Statistiques basiques
        $stats = [
            'teams_count' => $user->getTeamMemberships()->count(),
            'owned_teams_count' => $user->getOwnedTeams()->count(),
            'notifications_count' => $user->getNotifications()->filter(fn($n) => !$n->isRead())->count(),
            'account_age_days' => $user->getCreatedAt()->diff(new \DateTime())->days,
        ];
        
        return $this->render('user/statistics.html.twig', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

   
    #[Route('/apply-coach', name: 'user_apply_coach')]
public function applyCoach(
    Request $request, 
    EntityManagerInterface $em,
    SluggerInterface $slugger
): Response {
    $this->denyAccessUnlessGranted('ROLE_USER');
    
    /** @var User $user */
    $user = $this->getUser();
    
    // Vérifier si l'utilisateur a déjà une demande en cours
    $existingApplication = $em->getRepository(CoachApplication::class)
        ->findOneBy([
            'user' => $user,
            'status' => ApplicationStatus::PENDING
        ]);
        
    if ($existingApplication) {
        $this->addFlash('warning', 'Vous avez déjà une demande en cours de traitement.');
        return $this->redirectToRoute('user_profile');
    }
    
    // Vérifier si l'utilisateur est déjà coach
    if (in_array('ROLE_COACH', $user->getRoles())) {
        $this->addFlash('info', 'Vous êtes déjà coach !');
        return $this->redirectToRoute('user_profile');
    }
    
    $application = new CoachApplication();
    $application->setUser($user);
    
    $form = $this->createForm(CoachApplicationType::class, $application);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Gérer l'upload du CV
        $cvFile = $form->get('cvFileUpload')->getData();
        
        if ($cvFile) {
            $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$cvFile->guessExtension();

            try {
                $cvFile->move(
                    $this->getParameter('cv_directory'),
                    $newFilename
                );
                $application->setCvFile($newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l\'upload du CV.');
            }
        }
        
        $em->persist($application);
        $em->flush();
        
        $this->addFlash('success', 'Votre demande pour devenir coach a été soumise avec succès !');
        return $this->redirectToRoute('user_profile');
    }

    return $this->render('coach/application.html.twig', [
        'form' => $form->createView(),
    ]);
}
}