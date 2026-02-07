<?php

namespace App\Repository;

use App\Entity\Tournoi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tournoi>
 *
 * @method Tournoi|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tournoi|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tournoi[]    findAll()
 * @method Tournoi[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TournoiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournoi::class);
    }
    public function findBySearchAndFilter(?string $search, ?string $type, string $sort, string $direction): array
    {
        $qb = $this->createQueryBuilder('t');

        if ($search) {
            $qb->andWhere('t.nom LIKE :search OR t.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type) {
            // Assuming the database stores the backed value of the enum
            $qb->andWhere('t.typeTournoi = :type')
               ->setParameter('type', $type);
        }

        $qb->orderBy('t.' . $sort, $direction);

        return $qb->getQuery()->getResult();
    }
}
