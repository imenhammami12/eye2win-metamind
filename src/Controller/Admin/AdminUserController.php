<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\AccountStatus;
use App\Entity\AuditLog;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    #[Route('/', name: 'admin_users_index')]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = $request->query->get('search', '');
        $roleFilter = $request->query->get('role', '');
        $statusFilter = $request->query->get('status', '');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortOrder = $request->query->get('order', 'DESC');
        
        // Validate sort parameters
        $validSortFields = ['username', 'email', 'fullName', 'createdAt'];
        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'createdAt';
        }
        
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'DESC';
        }
        
        $queryBuilder = $userRepository->createQueryBuilder('u')
            ->orderBy('u.' . $sortBy, $sortOrder);
        
        // Recherche par nom, email ou username
        if ($search) {
            $queryBuilder->andWhere('u.username LIKE :search OR u.email LIKE :search OR u.fullName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre par rôle
        if ($roleFilter) {
            $queryBuilder->andWhere('u.rolesJson LIKE :role')
                ->setParameter('role', '%' . $roleFilter . '%');
        }
        
        // Filtre par statut
        if ($statusFilter) {
            $queryBuilder->andWhere('u.accountStatus = :status')
                ->setParameter('status', $statusFilter);
        }
        
        $users = $queryBuilder->getQuery()->getResult();
        
        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'accountStatuses' => AccountStatus::cases(),
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
    }
    
    #[Route('/{id}', name: 'admin_users_show', requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        // Calculer les statistiques de l'utilisateur
        $stats = [
            'totalConnections' => 0, // À implémenter avec un système de tracking
            'totalTimeSpent' => 0,   // À implémenter
            'teamsCount' => $user->getTeamMemberships()->count(),
            'ownedTeamsCount' => $user->getOwnedTeams()->count(),
            'notificationsCount' => $user->getNotifications()->count(),
            'unreadNotifications' => $user->getNotifications()->filter(fn($n) => !$n->isRead())->count(),
        ];
        
        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }
    
    #[Route('/{id}/edit-role', name: 'admin_users_edit_role', methods: ['POST'])]
    public function editRole(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('edit-role-' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $newRole = $request->request->get('role');
        $validRoles = ['ROLE_USER', 'ROLE_COACH', 'ROLE_ADMIN'];
        
        if (!in_array($newRole, $validRoles)) {
            $this->addFlash('error', 'Rôle invalide');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        
        // Conserver ROLE_USER et ajouter le nouveau rôle
        $roles = ['ROLE_USER'];
        if ($newRole !== 'ROLE_USER') {
            $roles[] = $newRole;
        }
        
        $user->setRoles($roles);
        
        // Créer un audit log
        $this->createAuditLog(
            $em,
            'USER_ROLE_CHANGED',
            'User',
            $user->getId(),
            "Rôle changé en : $newRole"
        );
        
        $em->flush();
        
        $this->addFlash('success', 'Rôle de l\'utilisateur modifié avec succès');
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }
    
    #[Route('/{id}/suspend', name: 'admin_users_suspend', methods: ['POST'])]
    public function suspend(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('suspend-' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $user->setAccountStatus(AccountStatus::SUSPENDED);
        
        $this->createAuditLog(
            $em,
            'USER_SUSPENDED',
            'User',
            $user->getId(),
            'Compte suspendu par un administrateur'
        );
        
        $em->flush();
        
        $this->addFlash('warning', 'Utilisateur suspendu avec succès');
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }
    
    #[Route('/{id}/ban', name: 'admin_users_ban', methods: ['POST'])]
    public function ban(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('ban-' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $user->setAccountStatus(AccountStatus::BANNED);
        
        $this->createAuditLog(
            $em,
            'USER_BANNED',
            'User',
            $user->getId(),
            'Compte banni par un administrateur'
        );
        
        $em->flush();
        
        $this->addFlash('danger', 'Utilisateur banni avec succès');
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }
    
    #[Route('/{id}/activate', name: 'admin_users_activate', methods: ['POST'])]
    public function activate(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('activate-' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $user->setAccountStatus(AccountStatus::ACTIVE);
        
        $this->createAuditLog(
            $em,
            'USER_ACTIVATED',
            'User',
            $user->getId(),
            'Compte réactivé par un administrateur'
        );
        
        $em->flush();
        
        $this->addFlash('success', 'Utilisateur réactivé avec succès');
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }
    
    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('delete-' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }
        
        $userId = $user->getId();
        $username = $user->getUsername();
        
        $this->createAuditLog(
            $em,
            'USER_DELETED',
            'User',
            $userId,
            "Utilisateur $username supprimé par un administrateur"
        );
        
        $em->remove($user);
        $em->flush();
        
        $this->addFlash('success', 'Utilisateur supprimé avec succès');
        return $this->redirectToRoute('admin_users_index');
    }
    
    #[Route('/create', name: 'admin_users_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create-user', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF invalide');
            }
            
            $user = new User();
            $user->setEmail($request->request->get('email'));
            $user->setUsername($request->request->get('username'));
            $user->setFullName($request->request->get('fullName'));
            
            $password = $passwordHasher->hashPassword($user, $request->request->get('password'));
            $user->setPassword($password);
            
            $role = $request->request->get('role', 'ROLE_USER');
            $roles = ['ROLE_USER'];
            if ($role !== 'ROLE_USER') {
                $roles[] = $role;
            }
            $user->setRoles($roles);
            
            $this->createAuditLog(
                $em,
                'USER_CREATED',
                'User',
                null,
                "Nouvel utilisateur créé : " . $user->getUsername()
            );
            
            $em->persist($user);
            $em->flush();
            
            $this->addFlash('success', 'Utilisateur créé avec succès');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        
        return $this->render('admin/users/create.html.twig');
    }
    
    private function createAuditLog(
        EntityManagerInterface $em,
        string $action,
        string $entityType,
        ?int $entityId,
        string $details
    ): void {
        $auditLog = new AuditLog();
        $auditLog->setUser($this->getUser());
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setDetails($details);
        $auditLog->setIpAddress($_SERVER['REMOTE_ADDR'] ?? null);
        
        $em->persist($auditLog);
    }
}