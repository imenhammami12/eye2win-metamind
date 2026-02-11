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
     * Find all users with ROLE_ADMIN
     * 
     * @return User[]
     */
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.rolesJson LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by role
     * 
     * @return User[]
     */
    public function findUsersByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.rolesJson LIKE :role')
            ->setParameter('role', '%' . $role . '%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.rolesJson LIKE :role')
            ->setParameter('role', '%' . $role . '%')
            ->getQuery()
            ->getSingleScalarResult();
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
     * Find all admins (alternative method using PHP filtering)
     * Use this if the SQL LIKE method has issues
     * 
     * @return User[]
     */
    public function findAllAdmins(): array
    {
        $allUsers = $this->findAll();
        $admins = [];

        foreach ($allUsers as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $admins[] = $user;
            }
        }

        // Sort by username
        usort($admins, function($a, $b) {
            return strcmp($a->getUsername(), $b->getUsername());
        });

        return $admins;
    }
}