<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    //    /**
    //     * @return Message[] Returns an array of Message objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Message
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findForChannelVisible(int $channelId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.channel = :cid')
            ->andWhere('m.isDeleted = :del')
            ->setParameter('cid', $channelId)
            ->setParameter('del', false)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()->getResult();
    }

    public function findForChannelAll(int $channelId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.channel = :cid')
            ->setParameter('cid', $channelId)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAdminList(string $q = '', string $status = 'active', string $sort = 'sentAt', string $dir = 'desc'): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.channel', 'c')->addSelect('c');

        // Status filter
        if ($status === 'active') {
            $qb->andWhere('m.isDeleted = false');
        } elseif ($status === 'deleted') {
            $qb->andWhere('m.isDeleted = true');
        }

        // Search
        if ($q !== '') {
            $qb->andWhere('LOWER(m.content) LIKE :q OR LOWER(m.senderName) LIKE :q OR LOWER(m.senderEmail) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        // ✅ Sort whitelist
        $map = [
            'sentAt'  => 'm.sentAt',
            'channel' => 'c.name',
            'sender'  => 'm.senderName',
            'status'  => 'm.isDeleted',
            'id'      => 'm.id',
        ];

        $sortField = $map[$sort] ?? 'm.sentAt';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $qb->orderBy($sortField, $dir);

        // stable sort (évite les résultats “qui bougent”)
        if ($sortField !== 'm.id') {
            $qb->addOrderBy('m.id', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }


}
