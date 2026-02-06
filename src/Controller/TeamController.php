<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamMembership;
use App\Entity\MembershipStatus;
use App\Entity\MemberRole;
use App\Form\TeamType;
use App\Repository\TeamRepository;
use App\Repository\TeamMembershipRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/teams')]
#[IsGranted('ROLE_USER')]
class TeamController extends AbstractController
{
    #[Route('/', name: 'app_teams_index')]
    public function index(
        TeamRepository $teamRepository,
        TeamMembershipRepository $membershipRepository
    ): Response {
        $user = $this->getUser();
        
        // Teams owned by user
        $ownedTeams = $teamRepository->findByOwner($user);
        
        // Teams where user is a member
        $memberTeams = $teamRepository->findTeamsByMember($user);
        
        // Pending invitations
        $pendingInvitations = $membershipRepository->findPendingInvitations($user);
        
        // Pending requests (requests user has made)
        $pendingRequests = $membershipRepository->findUserPendingRequests($user);
        
        // All active teams (for browsing)
        $allTeams = $teamRepository->findAllActiveWithMembers();
        
        return $this->render('team/index.html.twig', [
            'ownedTeams' => $ownedTeams,
            'memberTeams' => $memberTeams,
            'pendingInvitations' => $pendingInvitations,
            'pendingRequests' => $pendingRequests,
            'allTeams' => $allTeams,
        ]);
    }

    #[Route('/create', name: 'app_teams_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFile = $form->get('logoFile')->getData();

            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                try {
                    $logoFile->move(
                        $this->getParameter('teams_directory'),
                        $newFilename
                    );
                    $team->setLogo($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading logo');
                }
            }

            $team->setOwner($this->getUser());
            
            // Create owner membership with joinedAt
            $membership = new TeamMembership();
            $membership->setTeam($team);
            $membership->setUser($this->getUser());
            $membership->setRole(MemberRole::OWNER);
            $membership->setStatus(MembershipStatus::ACTIVE);
            $membership->setJoinedAt(new \DateTime()); // IMPORTANT: Set joinedAt
            
            $em->persist($team);
            $em->persist($membership);
            $em->flush();

            $this->addFlash('success', 'Team created successfully!');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        return $this->render('team/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_teams_show', requirements: ['id' => '\d+'])]
    public function show(
        Team $team,
        TeamMembershipRepository $membershipRepository
    ): Response {
        $activeMembers = $membershipRepository->findActiveMembers($team);
        $activeMembersCount = $membershipRepository->countActiveMembers($team);
        $pendingRequests = $membershipRepository->findPendingRequests($team);
        $pendingRequestsCount = $membershipRepository->countPendingRequests($team);
        
        $user = $this->getUser();
        $userMembership = null;
        $hasPendingRequest = false;
        
        foreach ($activeMembers as $membership) {
            if ($membership->getUser() === $user) {
                $userMembership = $membership;
                break;
            }
        }
        
        // Check if user has pending request
        $hasPendingRequest = $membershipRepository->hasPendingRequest($team, $user);

        return $this->render('team/show.html.twig', [
            'team' => $team,
            'activeMembers' => $activeMembers,
            'activeMembersCount' => $activeMembersCount,
            'pendingRequests' => $pendingRequests,
            'pendingRequestsCount' => $pendingRequestsCount,
            'userMembership' => $userMembership,
            'hasPendingRequest' => $hasPendingRequest,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_teams_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Team $team,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        // Only owner can edit
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You are not authorized to edit this team');
        }

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFile = $form->get('logoFile')->getData();

            if ($logoFile) {
                // Delete old logo
                if ($team->getLogo()) {
                    $oldLogoPath = $this->getParameter('teams_directory') . '/' . $team->getLogo();
                    if (file_exists($oldLogoPath)) {
                        unlink($oldLogoPath);
                    }
                }

                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                try {
                    $logoFile->move(
                        $this->getParameter('teams_directory'),
                        $newFilename
                    );
                    $team->setLogo($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading logo');
                }
            }

            $em->flush();

            $this->addFlash('success', 'Team updated successfully!');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        return $this->render('team/edit.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/invite', name: 'app_teams_invite', methods: ['POST'])]
    public function invite(
        Request $request,
        Team $team,
        UserRepository $userRepository,
        TeamMembershipRepository $membershipRepository,
        EntityManagerInterface $em
    ): Response {
        // Only owner or co-captain can invite
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $userId = $request->request->get('user_id');
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error', 'User not found');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        // Check if already member or invited
        if ($membershipRepository->isMemberOrInvited($team, $user)) {
            $this->addFlash('warning', 'This user is already a member or has already been invited');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        // Check max members
        $activeMembersCount = $membershipRepository->countActiveMembers($team);
        if ($activeMembersCount >= $team->getMaxMembers()) {
            $this->addFlash('error', 'The team has reached the maximum number of members');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        $membership = new TeamMembership();
        $membership->setTeam($team);
        $membership->setUser($user);
        $membership->setRole(MemberRole::MEMBER);
        $membership->setStatus(MembershipStatus::INVITED);
        $membership->setInvitedAt(new \DateTime());
        
        $em->persist($membership);
        $em->flush();

        $this->addFlash('success', 'Invitation sent to ' . $user->getUsername());
        return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
    }

    #[Route('/invitation/{id}/accept', name: 'app_teams_invitation_accept', methods: ['POST'])]
    public function acceptInvitation(
        TeamMembership $membership,
        EntityManagerInterface $em
    ): Response {
        if ($membership->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() !== MembershipStatus::INVITED) {
            $this->addFlash('error', 'This invitation is no longer valid');
            return $this->redirectToRoute('app_teams_index');
        }

        try {
            // Use the accept() method which now handles joinedAt
            $membership->accept();
            $em->flush();

            $this->addFlash('success', 'You have joined the team ' . $membership->getTeam()->getName());
            return $this->redirectToRoute('app_teams_show', ['id' => $membership->getTeam()->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while accepting the invitation');
            return $this->redirectToRoute('app_teams_index');
        }
    }

    #[Route('/invitation/{id}/decline', name: 'app_teams_invitation_decline', methods: ['POST'])]
    public function declineInvitation(
        TeamMembership $membership,
        EntityManagerInterface $em
    ): Response {
        if ($membership->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() !== MembershipStatus::INVITED) {
            $this->addFlash('error', 'This invitation is no longer valid');
            return $this->redirectToRoute('app_teams_index');
        }

        $membership->decline();
        $em->flush();

        $this->addFlash('info', 'Invitation declined');
        return $this->redirectToRoute('app_teams_index');
    }

    #[Route('/{id}/leave', name: 'app_teams_leave', methods: ['POST'])]
    public function leave(
        Team $team,
        TeamMembershipRepository $membershipRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        // Owner cannot leave, must transfer ownership first
        if ($team->getOwner() === $user) {
            $this->addFlash('error', 'Owner cannot leave the team. Transfer ownership first.');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        $membership = $membershipRepository->findOneBy([
            'team' => $team,
            'user' => $user,
            'status' => MembershipStatus::ACTIVE
        ]);

        if ($membership) {
            $membership->setStatus(MembershipStatus::LEFT);
            $em->flush();
            $this->addFlash('success', 'You have left the team');
        }

        return $this->redirectToRoute('app_teams_index');
    }

    #[Route('/{id}/remove-member/{membershipId}', name: 'app_teams_remove_member', methods: ['POST'])]
    public function removeMember(
        Team $team,
        int $membershipId,
        TeamMembershipRepository $membershipRepository,
        EntityManagerInterface $em
    ): Response {
        // Only owner can remove members
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $membership = $membershipRepository->find($membershipId);

        if (!$membership || $membership->getTeam() !== $team) {
            throw $this->createNotFoundException();
        }

        // Cannot remove owner
        if ($membership->getRole() === MemberRole::OWNER) {
            $this->addFlash('error', 'Cannot remove the owner');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        $em->remove($membership);
        $em->flush();

        $this->addFlash('success', 'Member removed from team');
        return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
    }

    #[Route('/search-users', name: 'app_teams_search_users', methods: ['GET'])]
    public function searchUsers(
        Request $request,
        UserRepository $userRepository
    ): JsonResponse {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $users = $userRepository->searchForInvitation($query);
        
        $results = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
            ];
        }, $users);

        return $this->json($results);
    }

    #[Route('/{id}/request-join', name: 'app_teams_request_join', methods: ['POST'])]
    public function requestJoin(
        Team $team,
        TeamMembershipRepository $membershipRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        // Check if team is active
        if (!$team->isActive()) {
            $this->addFlash('error', 'This team is not active');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        // Check if already member, invited, or has pending request
        if ($membershipRepository->isMemberOrInvited($team, $user)) {
            $this->addFlash('warning', 'You already have a pending request or are already a member of this team');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        // Check max members
        $activeMembersCount = $membershipRepository->countActiveMembers($team);
        if ($activeMembersCount >= $team->getMaxMembers()) {
            $this->addFlash('error', 'The team has reached the maximum number of members');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        try {
            // Create join request - PENDING status, no joinedAt yet
            $membership = new TeamMembership();
            $membership->setTeam($team);
            $membership->setUser($user);
            $membership->setRole(MemberRole::MEMBER);
            $membership->setStatus(MembershipStatus::PENDING);
            $membership->setInvitedAt(new \DateTime());
            // Don't set joinedAt here - it will be set when accepted
            
            $em->persist($membership);
            $em->flush();

            $this->addFlash('success', 'Your membership request has been sent to the team owner');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while sending your request');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }
    }

    #[Route('/request/{id}/cancel', name: 'app_teams_request_cancel', methods: ['POST'])]
    public function cancelRequest(
        TeamMembership $membership,
        EntityManagerInterface $em
    ): Response {
        if ($membership->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() !== MembershipStatus::PENDING) {
            $this->addFlash('error', 'This request is no longer pending');
            return $this->redirectToRoute('app_teams_index');
        }

        $teamId = $membership->getTeam()->getId();
        
        $em->remove($membership);
        $em->flush();

        $this->addFlash('info', 'Membership request cancelled');
        return $this->redirectToRoute('app_teams_show', ['id' => $teamId]);
    }

    #[Route('/request/{id}/accept', name: 'app_teams_request_accept', methods: ['POST'])]
    public function acceptRequest(
        TeamMembership $membership,
        TeamMembershipRepository $membershipRepository,
        EntityManagerInterface $em
    ): Response {
        $team = $membership->getTeam();
        
        // Only owner can accept requests
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() !== MembershipStatus::PENDING) {
            $this->addFlash('error', 'This request is no longer pending');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        // Check max members
        $activeMembersCount = $membershipRepository->countActiveMembers($team);
        if ($activeMembersCount >= $team->getMaxMembers()) {
            $this->addFlash('error', 'The team has reached the maximum number of members');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        try {
            // Use the accept() method which now handles joinedAt
            $membership->accept();
            $em->flush();

            $this->addFlash('success', $membership->getUser()->getUsername() . ' has joined the team!');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while accepting the request');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }
    }

    #[Route('/request/{id}/reject', name: 'app_teams_request_reject', methods: ['POST'])]
    public function rejectRequest(
        TeamMembership $membership,
        EntityManagerInterface $em
    ): Response {
        $team = $membership->getTeam();
        
        // Only owner can reject requests
        if ($team->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getStatus() !== MembershipStatus::PENDING) {
            $this->addFlash('error', 'This request is no longer pending');
            return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
        }

        $username = $membership->getUser()->getUsername();
        
        $em->remove($membership);
        $em->flush();

        $this->addFlash('warning', 'Request from ' . $username . ' rejected');
        return $this->redirectToRoute('app_teams_show', ['id' => $team->getId()]);
    }
}