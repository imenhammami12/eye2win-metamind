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
        // ===== DATE RANGES =====
        $now = new \DateTime();
        $today = new \DateTime('today');
        $yesterday = new \DateTime('yesterday');
        $sevenDaysAgo = new \DateTime('-7 days');
        $thirtyDaysAgo = new \DateTime('-30 days');
        $previousMonthStart = new \DateTime('first day of last month');
        $previousMonthEnd = new \DateTime('last day of last month');
        $currentMonthStart = new \DateTime('first day of this month');
        
        // ===== USER STATISTICS =====
        $allUsers = $userRepository->findAll();
        $totalUsers = count($allUsers);
        $activeUsers = $userRepository->count(['accountStatus' => AccountStatus::ACTIVE]);
        $suspendedUsers = $userRepository->count(['accountStatus' => AccountStatus::SUSPENDED]);
        $bannedUsers = $userRepository->count(['accountStatus' => AccountStatus::BANNED]);
        
        // Count users by role
        $totalCoaches = count(array_filter($allUsers, fn($u) => in_array('ROLE_COACH', $u->getRoles())));
        $totalAdmins = count(array_filter($allUsers, fn($u) => in_array('ROLE_ADMIN', $u->getRoles())));
        $regularUsers = $totalUsers - $totalCoaches - $totalAdmins;
        
        // Time-based user stats
        $usersToday = count(array_filter($allUsers, fn($u) => $u->getCreatedAt() >= $today));
        $usersYesterday = count(array_filter($allUsers, fn($u) => 
            $u->getCreatedAt() >= $yesterday && $u->getCreatedAt() < $today
        ));
        $usersLast7Days = count(array_filter($allUsers, fn($u) => $u->getCreatedAt() >= $sevenDaysAgo));
        $usersLast30Days = count(array_filter($allUsers, fn($u) => $u->getCreatedAt() >= $thirtyDaysAgo));
        $usersThisMonth = count(array_filter($allUsers, fn($u) => $u->getCreatedAt() >= $currentMonthStart));
        $usersLastMonth = count(array_filter($allUsers, fn($u) => 
            $u->getCreatedAt() >= $previousMonthStart && $u->getCreatedAt() <= $previousMonthEnd
        ));
        
        // User growth rate calculation
        $userGrowthRate = $usersLastMonth > 0 
            ? round((($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100, 1)
            : 0;
        
        // Average users per day (last 30 days)
        $avgUsersPerDay = round($usersLast30Days / 30, 1);
        
        // ===== TEAM STATISTICS =====
        $allTeams = $teamRepository->findAll();
        $totalTeams = count($allTeams);
        $activeTeams = $teamRepository->count(['isActive' => true]);
        $inactiveTeams = $teamRepository->count(['isActive' => false]);
        
        // Time-based team stats
        $teamsToday = count(array_filter($allTeams, fn($t) => $t->getCreatedAt() >= $today));
        $teamsLast7Days = count(array_filter($allTeams, fn($t) => $t->getCreatedAt() >= $sevenDaysAgo));
        $teamsLast30Days = count(array_filter($allTeams, fn($t) => $t->getCreatedAt() >= $thirtyDaysAgo));
        $teamsThisMonth = count(array_filter($allTeams, fn($t) => $t->getCreatedAt() >= $currentMonthStart));
        $teamsLastMonth = count(array_filter($allTeams, fn($t) => 
            $t->getCreatedAt() >= $previousMonthStart && $t->getCreatedAt() <= $previousMonthEnd
        ));
        
        // Team growth rate
        $teamGrowthRate = $teamsLastMonth > 0 
            ? round((($teamsThisMonth - $teamsLastMonth) / $teamsLastMonth) * 100, 1)
            : 0;
        
        // Team member statistics
        $totalMembers = $membershipRepository->count([]);
        $activeMembers = count($membershipRepository->findBy(['status' => \App\Entity\MembershipStatus::ACTIVE]));
        $avgMembersPerTeam = $totalTeams > 0 ? round($totalMembers / $totalTeams, 1) : 0;
        
        // ===== COACH APPLICATION STATISTICS =====
        $allApplications = $applicationRepository->findAll();
        $totalApplications = count($allApplications);
        $pendingApplications = $applicationRepository->count(['status' => ApplicationStatus::PENDING]);
        $approvedApplications = $applicationRepository->count(['status' => ApplicationStatus::APPROVED]);
        $rejectedApplications = $applicationRepository->count(['status' => ApplicationStatus::REJECTED]);
        
        // Time-based application stats
        $applicationsToday = count(array_filter($allApplications, fn($a) => $a->getSubmittedAt() >= $today));
        $applicationsLast7Days = count(array_filter($allApplications, fn($a) => $a->getSubmittedAt() >= $sevenDaysAgo));
        $applicationsLast30Days = count(array_filter($allApplications, fn($a) => $a->getSubmittedAt() >= $thirtyDaysAgo));
        
        // Application approval rate
        $approvalRate = $totalApplications > 0 
            ? round(($approvedApplications / $totalApplications) * 100, 1)
            : 0;
        
        // Average processing time (if you track this)
        // $avgProcessingTime = $this->calculateAvgProcessingTime($applicationRepository);
        
        // ===== NOTIFICATION STATISTICS =====
        $totalNotifications = $notificationRepository->count([]);
        $unreadNotifications = $notificationRepository->count(['isRead' => false]);
        $readRate = $totalNotifications > 0 
            ? round((($totalNotifications - $unreadNotifications) / $totalNotifications) * 100, 1)
            : 0;
        
        // ===== AUDIT LOG STATISTICS =====
        $totalAuditLogs = $auditLogRepository->count([]);
        $recentLogs = $auditLogRepository->findBy([], ['createdAt' => 'DESC'], 10);
        
        // ===== ACTIVITY METRICS =====
        $latestUsers = $userRepository->findBy([], ['createdAt' => 'DESC'], 5);
        $latestTeams = $teamRepository->findBy([], ['createdAt' => 'DESC'], 5);
        $latestApplications = $applicationRepository->findBy([], ['submittedAt' => 'DESC'], 5);
        
        // ===== ENGAGEMENT METRICS =====
        $activeUsersPercentage = $totalUsers > 0 
            ? round(($activeUsers / $totalUsers) * 100, 1)
            : 0;
        
        $activeTeamsPercentage = $totalTeams > 0 
            ? round(($activeTeams / $totalTeams) * 100, 1)
            : 0;
        
        // ===== CHART DATA (for last 7 days) =====
        $chartData = $this->generateChartData($allUsers, $allTeams, $allApplications, $sevenDaysAgo);
        
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
            'usersToday' => $usersToday,
            'usersYesterday' => $usersYesterday,
            'usersLast7Days' => $usersLast7Days,
            'usersLast30Days' => $usersLast30Days,
            'usersThisMonth' => $usersThisMonth,
            'userGrowthRate' => $userGrowthRate,
            'avgUsersPerDay' => $avgUsersPerDay,
            'activeUsersPercentage' => $activeUsersPercentage,
            
            // Team stats
            'totalTeams' => $totalTeams,
            'activeTeams' => $activeTeams,
            'inactiveTeams' => $inactiveTeams,
            'teamsToday' => $teamsToday,
            'teamsLast7Days' => $teamsLast7Days,
            'teamsLast30Days' => $teamsLast30Days,
            'teamsThisMonth' => $teamsThisMonth,
            'teamGrowthRate' => $teamGrowthRate,
            'totalMembers' => $totalMembers,
            'activeMembers' => $activeMembers,
            'avgMembersPerTeam' => $avgMembersPerTeam,
            'activeTeamsPercentage' => $activeTeamsPercentage,
            
            // Application stats
            'totalApplications' => $totalApplications,
            'pendingApplications' => $pendingApplications,
            'approvedApplications' => $approvedApplications,
            'rejectedApplications' => $rejectedApplications,
            'applicationsToday' => $applicationsToday,
            'applicationsLast7Days' => $applicationsLast7Days,
            'applicationsLast30Days' => $applicationsLast30Days,
            'approvalRate' => $approvalRate,
            
            // Other stats
            'totalNotifications' => $totalNotifications,
            'unreadNotifications' => $unreadNotifications,
            'readRate' => $readRate,
            'totalAuditLogs' => $totalAuditLogs,
            
            // Activity & Lists
            'recentLogs' => $recentLogs,
            'latestUsers' => $latestUsers,
            'latestTeams' => $latestTeams,
            'latestApplications' => $latestApplications,
            
            // Chart data
            'chartData' => $chartData,
        ]);
    }
    
    /**
     * Generate chart data for the last 7 days
     */
    private function generateChartData(array $users, array $teams, array $applications, \DateTime $startDate): array
    {
        $data = [
            'labels' => [],
            'users' => [],
            'teams' => [],
            'applications' => [],
        ];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $dateStart = clone $date;
            $dateStart->setTime(0, 0, 0);
            $dateEnd = clone $date;
            $dateEnd->setTime(23, 59, 59);
            
            $data['labels'][] = $date->format('d/m');
            
            $data['users'][] = count(array_filter($users, function($u) use ($dateStart, $dateEnd) {
                return $u->getCreatedAt() >= $dateStart && $u->getCreatedAt() <= $dateEnd;
            }));
            
            $data['teams'][] = count(array_filter($teams, function($t) use ($dateStart, $dateEnd) {
                return $t->getCreatedAt() >= $dateStart && $t->getCreatedAt() <= $dateEnd;
            }));
            
            $data['applications'][] = count(array_filter($applications, function($a) use ($dateStart, $dateEnd) {
                return $a->getSubmittedAt() >= $dateStart && $a->getSubmittedAt() <= $dateEnd;
            }));
        }
        
        return $data;
    }
}