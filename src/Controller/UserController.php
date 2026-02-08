<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\CoachApplication;
use App\Entity\ApplicationStatus;
use App\Form\UserProfileType;
use App\Form\CoachApplicationType;
use App\Repository\UserRepository;
use App\Repository\NotificationRepository;
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
    public function profile(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        /** @var User $user */
        $user = $this->getUser();
        
        $coachApplications = $em->getRepository(CoachApplication::class)
            ->findBy(['user' => $user], ['submittedAt' => 'DESC']);
        
        $latestCoachApplication = !empty($coachApplications) ? $coachApplications[0] : null;
        
        $hasPendingApplication = false;
        if ($latestCoachApplication && $latestCoachApplication->getStatus() === ApplicationStatus::PENDING) {
            $hasPendingApplication = true;
        }
        
        $stats = [
            'teams_count' => $user->getTeamMemberships()->count(),
            'owned_teams_count' => $user->getOwnedTeams()->count(),
        ];
        
        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'latestCoachApplication' => $latestCoachApplication,
            'hasPendingApplication' => $hasPendingApplication,
            'stats' => $stats,
        ]);
    }

    #[Route('/edit', name: 'user_edit_profile')]
    public function editProfile(
        Request $request, 
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        UserRepository $userRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // NIVEAU 2 : Validation côté serveur
            $newEmail = $form->get('email')->getData();
            if ($newEmail !== $user->getEmail()) {
                $existingUser = $userRepository->findOneBy(['email' => strtolower(trim($newEmail))]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', 'This email address is already registered by another user.');
                    return $this->render('user/edit_profile.html.twig', [
                        'form' => $form->createView(),
                        'user' => $user,
                    ]);
                }
            }
            
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
                    $this->addFlash('error', 'Error uploading image.');
                }
            }

            $em->flush();
            
            $this->addFlash('success', 'Your profile has been successfully updated!');
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
        
        if (in_array('ROLE_COACH', $user->getRoles())) {
            $this->addFlash('info', 'You are already a coach!');
            return $this->redirectToRoute('user_profile');
        }
        
        $existingApplication = $em->getRepository(CoachApplication::class)
            ->findOneBy([
                'user' => $user,
                'status' => ApplicationStatus::PENDING
            ]);
            
        if ($existingApplication) {
            $this->addFlash('warning', 'You already have a pending application.');
            return $this->redirectToRoute('user_profile');
        }
        
        $application = new CoachApplication();
        $application->setUser($user);
        
        $form = $this->createForm(CoachApplicationType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
                    $this->addFlash('error', 'Error uploading CV.');
                }
            }
            
            $em->persist($application);
            $em->flush();
            
            $this->addFlash('success', 'Your coach application has been submitted successfully!');
            return $this->redirectToRoute('user_profile');
        }

        return $this->render('coach/application.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/notification/{id}/read', name: 'user_notification_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markNotificationRead(int $id, Request $request, NotificationRepository $notificationRepo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->isCsrfTokenValid('notification_read_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid token.');
        }
        $notification = $notificationRepo->find($id);
        if (!$notification || $notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Notification not found.');
        }
        $notification->markAsRead();
        $em->flush();
        $target = $request->headers->get('Referer') ?: $this->generateUrl('community_channels_index');
        return $this->redirect($target);
    }
}