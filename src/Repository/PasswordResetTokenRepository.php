<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    /**
     * Clean up expired tokens (should be run periodically)
     */
    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('prt')
            ->delete()
            ->where('prt.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all tokens for a user
     */
    public function deleteUserTokens(int $userId): int
    {
        return $this->createQueryBuilder('prt')
            ->delete()
            ->where('prt.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}