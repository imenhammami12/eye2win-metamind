<?php

namespace App\Repository;

use App\Entity\LiveStream;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LiveStreamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveStream::class);
    }

    public function findActiveLives(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.status = :status')
            ->setParameter('status', 'live')
            ->orderBy('l.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findScheduledAndLive(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.status IN (:statuses)')
            ->setParameter('statuses', ['scheduled', 'live'])
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
