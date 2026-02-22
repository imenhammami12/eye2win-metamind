<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\MembershipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /**
     * Find all active teams with their memberships
     */
    public function findAllActiveWithMembers(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.teamMemberships', 'tm')
            ->leftJoin('tm.user', 'u')
            ->leftJoin('t.owner', 'o')
            ->addSelect('tm', 'u', 'o')
            ->where('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find teams by owner
     */
    public function findByOwner($user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.teamMemberships', 'tm')
            ->addSelect('tm')
            ->where('t.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find teams where user is a member
     */
    public function findTeamsByMember($user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.teamMemberships', 'tm')
            ->leftJoin('t.owner', 'o')
            ->addSelect('tm', 'o')
            ->where('tm.user = :user')
            ->andWhere('tm.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MembershipStatus::ACTIVE)
            ->orderBy('tm.joinedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get team statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('t');
        
        return [
            'total' => $this->count([]),
            'active' => $this->count(['isActive' => true]),
            'inactive' => $this->count(['isActive' => false]),
        ];
    }

    /**
     * Search teams by name
     */
    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.owner', 'o')
            ->addSelect('o')
            ->where('t.name LIKE :query')
            ->andWhere('t.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}