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
    } /// returns only channels that are approved + active /// used in channelController index+show

    public function findAdminList(string $q, string $status, string $type, string $active, string $sort, string $dir): array
    {
        $qb = $this->createQueryBuilder('c');

        // Search
        if ($q !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :q OR LOWER(c.game) LIKE :q OR LOWER(c.createdBy) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        // Status
        if ($status !== 'all') {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }

        // Type
        if ($type !== 'all') {
            $qb->andWhere('c.type = :type')->setParameter('type', $type);
        }

        // Active
        if ($active !== 'all') {
            $qb->andWhere('c.isActive = :active')->setParameter('active', $active === '1');
        }
        $sortMap = [
            'id'        => 'c.id',
            'name'      => 'c.name',
            'game'      => 'c.game',
            'type'      => 'c.type',
            'status'    => 'c.status',
            'active'    => 'c.isActive',
            'createdAt' => 'c.createdAt',
            'createdBy' => 'c.createdBy',
            'approvedAt'=> 'c.approvedAt',
        ];

        $sortExpr = $sortMap[$sort] ?? 'c.createdAt';
        $dirSql   = strtoupper($dir) === 'asc' ? 'ASC' : 'DESC';

        $qb->orderBy($sortExpr, $dirSql);

        if($sortExpr !== 'c.id'){
            $qb->addOrderBy('c.id','DESC');
        }
        return $qb->getQuery()->getResult();

        /*return $qb
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy($sortExpr, $dirSql)
            ->getQuery()
            ->getResult();*/
    }/// used in adminchannelcontroller for filtering

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :s')
            ->setParameter('s', $status)
            ->getQuery()
            ->getSingleScalarResult();
    } /// count channels by status user in adminchannelcontroller for pending count in admin ui


}
