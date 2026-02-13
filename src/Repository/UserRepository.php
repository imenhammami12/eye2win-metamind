<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Find all users with ROLE_ADMIN or ROLE_SUPER_ADMIN
     * 
     * IMPORTANT: Cannot use SQL LIKE because SUPER_ADMIN doesn't contain "ADMIN" string
     * Must load all users and filter in PHP to respect role hierarchy
     * 
     * @return User[]
     */
    public function findAdmins(): array
    {
        // Get all users and filter by role in PHP
        // This is the ONLY reliable way to respect Symfony's role hierarchy
        $allUsers = $this->findAll();
        $admins = [];
        
        foreach ($allUsers as $user) {
            $roles = $user->getRoles();
            // Check if user has ROLE_ADMIN or ROLE_SUPER_ADMIN
            if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true)) {
                $admins[] = $user;
            }
        }
        
        // Sort by username
        usort($admins, function($a, $b) {
            return strcmp($a->getUsername(), $b->getUsername());
        });
        
        return $admins;
    }

    /**
     * Find users by role
     * 
     * @return User[]
     */
    public function findUsersByRole(string $role): array
    {
        $allUsers = $this->findAll();
        return array_filter($allUsers, fn(User $user) => in_array($role, $user->getRoles(), true));
    }

    /**
     * Search users by username or email (for team invitations)
     * 
     * @return User[]
     */
    public function searchForInvitation(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.email LIKE :query')
            ->andWhere('u.accountStatus = :status')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('status', \App\Entity\AccountStatus::ACTIVE)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active users
     * 
     * @return User[]
     */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.accountStatus = :status')
            ->setParameter('status', \App\Entity\AccountStatus::ACTIVE)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search users by username or email
     * 
     * @return User[]
     */
    public function searchUsers(string $search): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username LIKE :search OR u.email LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total users
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active users
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.accountStatus = :status')
            ->setParameter('status', \App\Entity\AccountStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users by role
     */
    public function countByRole(string $role): int
    {
        return count($this->findUsersByRole($role));
    }

    /**
     * Find recent users (registered in last X days)
     * 
     * @return User[]
     */
    public function findRecent(int $days = 30): array
    {
        $date = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('u')
            ->where('u.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Alternative method: Find all admins using SQL OR condition
     * Use this if performance is an issue
     * 
     * @return User[]
     */
    public function findAdminsViaSQL(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.rolesJson LIKE :admin OR u.rolesJson LIKE :superadmin')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->setParameter('superadmin', '%ROLE_SUPER_ADMIN%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }
}