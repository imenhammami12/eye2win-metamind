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
     * Find users by role
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
}