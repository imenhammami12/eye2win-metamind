<?php

namespace App\Repository;

use App\Entity\TeamMembership;
use App\Entity\MembershipStatus;
use App\Entity\User;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TeamMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMembership::class);
    }

    /**
     * Find pending invitations for a user
     */
    public function findPendingInvitations(User $user): array
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.team', 't')
            ->leftJoin('t.owner', 'o')
            ->addSelect('t', 'o')
            ->where('tm.user = :user')
            ->andWhere('tm.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MembershipStatus::INVITED)
            ->orderBy('tm.invitedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active members of a team
     */
    public function findActiveMembers(Team $team): array
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.user', 'u')
            ->addSelect('u')
            ->where('tm.team = :team')
            ->andWhere('tm.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', MembershipStatus::ACTIVE)
            ->orderBy('tm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user is already member or invited
     */

/**
 * Check if user is already member, invited, or has pending request
 */
public function isMemberOrInvited(Team $team, User $user): bool
{
    $result = $this->createQueryBuilder('tm')
        ->select('COUNT(tm.id)')
        ->where('tm.team = :team')
        ->andWhere('tm.user = :user')
        ->andWhere('tm.status IN (:statuses)')
        ->setParameter('team', $team)
        ->setParameter('user', $user)
        ->setParameter('statuses', [MembershipStatus::INVITED, MembershipStatus::ACTIVE, MembershipStatus::PENDING])
        ->getQuery()
        ->getSingleScalarResult();

    return $result > 0;
}

    /**
     * Count active members of a team
     */
    public function countActiveMembers(Team $team): int
    {
        return $this->count([
            'team' => $team,
            'status' => MembershipStatus::ACTIVE
        ]);
    }

    /**
 * Find pending join requests for a team
 */
public function findPendingRequests(Team $team): array
{
    return $this->createQueryBuilder('tm')
        ->leftJoin('tm.user', 'u')
        ->addSelect('u')
        ->where('tm.team = :team')
        ->andWhere('tm.status = :status')
        ->setParameter('team', $team)
        ->setParameter('status', MembershipStatus::PENDING)
        ->orderBy('tm.invitedAt', 'DESC')
        ->getQuery()
        ->getResult();
}

/**
 * Count pending join requests for a team
 */
public function countPendingRequests(Team $team): int
{
    return $this->count([
        'team' => $team,
        'status' => MembershipStatus::PENDING
    ]);
}

/**
 * Find pending requests for a user (requests user has made)
 */
public function findUserPendingRequests(User $user): array
{
    return $this->createQueryBuilder('tm')
        ->leftJoin('tm.team', 't')
        ->leftJoin('t.owner', 'o')
        ->addSelect('t', 'o')
        ->where('tm.user = :user')
        ->andWhere('tm.status = :status')
        ->setParameter('user', $user)
        ->setParameter('status', MembershipStatus::PENDING)
        ->orderBy('tm.invitedAt', 'DESC')
        ->getQuery()
        ->getResult();
}

/**
 * Check if user has pending request for team
 */
public function hasPendingRequest(Team $team, User $user): bool
{
    $result = $this->createQueryBuilder('tm')
        ->select('COUNT(tm.id)')
        ->where('tm.team = :team')
        ->andWhere('tm.user = :user')
        ->andWhere('tm.status = :status')
        ->setParameter('team', $team)
        ->setParameter('user', $user)
        ->setParameter('status', MembershipStatus::PENDING)
        ->getQuery()
        ->getSingleScalarResult();

    return $result > 0;
}
}