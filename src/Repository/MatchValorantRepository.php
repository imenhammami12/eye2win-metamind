<?php

namespace App\Repository;

use App\Entity\MatchValorant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MatchValorantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchValorant::class);
    }

    /**
     * @param array{player?:string,team?:string,match?:string,archived?:string} $filters
     * @return MatchValorant[]
     */
    public function searchForDashboard(User $owner, array $filters): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.equipes', 'e')->addSelect('e')
            ->leftJoin('m.joueurs', 'j')->addSelect('j')
            ->leftJoin('j.statistique', 's')->addSelect('s')
            ->andWhere('m.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('m.playedAt', 'DESC')
            ->addOrderBy('m.id', 'DESC');

        if (!empty($filters['player'])) {
            $qb->andWhere('j.riotName LIKE :player OR j.riotTag LIKE :player')
                ->setParameter('player', '%' . trim((string) $filters['player']) . '%');
        }

        if (!empty($filters['team'])) {
            $qb->andWhere('e.name LIKE :team')
                ->setParameter('team', '%' . trim((string) $filters['team']) . '%');
        }

        if (!empty($filters['match'])) {
            $match = trim((string) $filters['match']);
            $qb->andWhere('m.trackerMatchId LIKE :match OR m.mapName LIKE :match')
                ->setParameter('match', '%' . $match . '%');
        }

        if (($filters['archived'] ?? '') === 'only') {
            $qb->andWhere('m.archivedAt IS NOT NULL');
        } elseif (($filters['archived'] ?? '') !== 'with') {
            $qb->andWhere('m.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOwnedById(User $owner, int $id): ?MatchValorant
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.equipes', 'e')->addSelect('e')
            ->leftJoin('m.joueurs', 'j')->addSelect('j')
            ->leftJoin('j.statistique', 's')->addSelect('s')
            ->andWhere('m.owner = :owner')
            ->andWhere('m.id = :id')
            ->setParameter('owner', $owner)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
