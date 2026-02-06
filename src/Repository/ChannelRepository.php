<?php

namespace App\Repository;

use App\Entity\Channel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Channel>
 */
class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Channel::class);
    }

    //    /**
    //     * @return Channel[] Returns an array of Channel objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Channel
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findVisibleForUser(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.status = :approved')
            ->andWhere('c.isActive = :active')
            ->setParameter('approved', \App\Entity\Channel::STATUS_APPROVED)
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'DESC')
            ;

        return $qb->getQuery()->getResult();

    }

    public function findPending(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :pending')
            ->setParameter('pending', \App\Entity\Channel::STATUS_PENDING)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

}
