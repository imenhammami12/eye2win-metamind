<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Repository\TeamRepository;
use App\Repository\CoachApplicationRepository;
use App\Repository\TeamMembershipRepository;
use App\Repository\NotificationRepository;
use App\Repository\AuditLogRepository;
use App\Entity\AccountStatus;
use App\Entity\ApplicationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_index')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }
        
        return $this->redirectToRoute('admin_login');
    }
    
    #[Route('/dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(
        UserRepository $userRepository,
        TeamRepository $teamRepository,
        CoachApplicationRepository $applicationRepository,
        TeamMembershipRepository $membershipRepository,
        NotificationRepository $notificationRepository,
        AuditLogRepository $auditLogRepository
    ): Response {
        // ===== USER STATISTICS =====
        $totalUsers = $userRepository->count([]);
        $activeUsers = $userRepository->count(['accountStatus' => AccountStatus::ACTIVE]);
        $suspendedUsers = $userRepository->count(['accountStatus' => AccountStatus::SUSPENDED]);
        $bannedUsers = $userRepository->count(['accountStatus' => AccountStatus::BANNED]);
        
        // Count users by role
        $allUsers = $userRepository->findAll();
        $totalCoaches = count(array_filter($allUsers, fn($u) => in_array('ROLE_COACH', $u->getRoles())));
        $totalAdmins = count(array_filter($allUsers, fn($u) => in_array('ROLE_ADMIN', $u->getRoles())));
        $regularUsers = $totalUsers - $totalCoaches - $totalAdmins;
        
        // Recent users (last 7 days)
        $sevenDaysAgo = new \DateTime('-7 days');
        $recentUsers = count(array_filter($allUsers, fn($u) => $u->getCreatedAt() >= $sevenDaysAgo));
        
        // ===== TEAM STATISTICS =====
        $totalTeams = $teamRepository->count([]);
        $activeTeams = $teamRepository->count(['isActive' => true]);
        $inactiveTeams = $teamRepository->count(['isActive' => false]);
        
        // Teams created in last 7 days
        $allTeams = $teamRepository->findAll();
        $recentTeams = count(array_filter($allTeams, fn($t) => $t->getCreatedAt() >= $sevenDaysAgo));
        
        // Total team members
        $totalMembers = $membershipRepository->count([]);
        $activeMembers = count($membershipRepository->findBy(['status' => \App\Entity\MembershipStatus::ACTIVE]));
        
        // ===== COACH APPLICATION STATISTICS =====
        $totalApplications = $applicationRepository->count([]);
        $pendingApplications = $applicationRepository->count(['status' => ApplicationStatus::PENDING]);
        $approvedApplications = $applicationRepository->count(['status' => ApplicationStatus::APPROVED]);
        $rejectedApplications = $applicationRepository->count(['status' => ApplicationStatus::REJECTED]);
        
        // Recent applications (last 7 days)
        $allApplications = $applicationRepository->findAll();
        $recentApplications = count(array_filter($allApplications, fn($a) => $a->getSubmittedAt() >= $sevenDaysAgo));
        
        // ===== NOTIFICATION STATISTICS =====
        $totalNotifications = $notificationRepository->count([]);
        $unreadNotifications = $notificationRepository->count(['isRead' => false]);
        
        // ===== AUDIT LOG STATISTICS =====
        $totalAuditLogs = $auditLogRepository->count([]);
        
        // ===== ACTIVITY TIMELINE (Recent actions) =====
        $recentLogs = $auditLogRepository->findBy([], ['createdAt' => 'DESC'], 10);
        
        // ===== LATEST REGISTRATIONS =====
        $latestUsers = $userRepository->findBy([], ['createdAt' => 'DESC'], 5);
        
        // ===== GROWTH TRENDS (Last 30 days) =====
        $thirtyDaysAgo = new \DateTime('-30 days');
        $usersLast30Days = count(array_filter($allUsers, fn($u) => $u->getCreatedAt() >= $thirtyDaysAgo));
        $teamsLast30Days = count(array_filter($allTeams, fn($t) => $t->getCreatedAt() >= $thirtyDaysAgo));
        
        return $this->render('admin/dashboard.html.twig', [
            'user' => $this->getUser(),
            
            // User stats
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'suspendedUsers' => $suspendedUsers,
            'bannedUsers' => $bannedUsers,
            'totalCoaches' => $totalCoaches,
            'totalAdmins' => $totalAdmins,
            'regularUsers' => $regularUsers,
            'recentUsers' => $recentUsers,
            'usersLast30Days' => $usersLast30Days,
            
            // Team stats
            'totalTeams' => $totalTeams,
            'activeTeams' => $activeTeams,
            'inactiveTeams' => $inactiveTeams,
            'recentTeams' => $recentTeams,
            'totalMembers' => $totalMembers,
            'activeMembers' => $activeMembers,
            'teamsLast30Days' => $teamsLast30Days,
            
            // Application stats
            'totalApplications' => $totalApplications,
            'pendingApplications' => $pendingApplications,
            'approvedApplications' => $approvedApplications,
            'rejectedApplications' => $rejectedApplications,
            'recentApplications' => $recentApplications,
            
            // Other stats
            'totalNotifications' => $totalNotifications,
            'unreadNotifications' => $unreadNotifications,
            'totalAuditLogs' => $totalAuditLogs,
            
            // Activity & Lists
            'recentLogs' => $recentLogs,
            'latestUsers' => $latestUsers,
            'pendingApplications' => $pendingApplications,
        ]);
    }
}