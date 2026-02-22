<?php

namespace App\Repository;

use App\Entity\Channel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

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

    public function findVisibleForUser(?UserInterface $user = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.status = :approved')
            ->andWhere('c.isActive = :active')
            ->setParameter('approved', \App\Entity\Channel::STATUS_APPROVED)
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'DESC')
            ;
        // visitor => only public
        if ($user === null) {
            $qb->andWhere('c.type = :public')
                ->setParameter('public', Channel::TYPE_PUBLIC);
            return $qb->getQuery()->getResult();
        }

        // user => public OR createdBy = me
    $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
    $isAdmin = in_array('ROLE_ADMIN', $roles, true);

    if (!$isAdmin) {
        $qb->andWhere('c.type = :public OR c.createdBy = :me')
            ->setParameter('public', Channel::TYPE_PUBLIC)
            ->setParameter('me', $user->getUserIdentifier());
    }

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

        $qb->orderBy('LOWER(' . $sortExpr . ')', $dirSql);

        if($sortExpr !== "c.id"){
            $qb->addOrderBy("c.id", $dirSql);
        }

        return $qb->getQuery()->getResult();

        /*if($sortExpr !== 'c.id'){
            $qb->addOrderBy('c.id','DESC');
        }
        return $qb->getQuery()->getResult();

        return $qb
            //->orderBy('c.id', 'DESC')
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
