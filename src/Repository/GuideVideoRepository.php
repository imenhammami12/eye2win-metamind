<?php

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\Game;
use App\Entity\GuideVideo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GuideVideo>
 *
 * @method GuideVideo|null find($id, $lockMode = null, $lockVersion = null)
 * @method GuideVideo|null findOneBy(array $criteria, array $orderBy = null)
 * @method GuideVideo[]    findAll()
 * @method GuideVideo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GuideVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuideVideo::class);
    }

    public function findApprovedByGame(Game $game, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.game = :game')
            ->andWhere('g.status = :status')
            ->setParameter('game', $game)
            ->setParameter('status', 'approved')
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findApprovedByGameAndAgent(Game $game, Agent $agent): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.game = :game')
            ->andWhere('g.agent = :agent')
            ->andWhere('g.status = :status')
            ->setParameter('game', $game)
            ->setParameter('agent', $agent)
            ->setParameter('status', 'approved')
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUploader(User $user): array
    {
        return $this->findBy(['uploadedBy' => $user], ['createdAt' => 'DESC']);
    }

    public function findPendingGuides(): array
    {
        return $this->findBy(['status' => 'pending'], ['createdAt' => 'ASC']);
    }

    public function countPendingGuides(): int
    {
        return $this->count(['status' => 'pending']);
    }

    public function findApprovedGuides(int $limit = 50): array
    {
        return $this->findBy(['status' => 'approved'], ['createdAt' => 'DESC'], $limit);
    }

    public function findPopularGuides(int $limit = 10): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.status = :status')
            ->setParameter('status', 'approved')
            ->orderBy('g.likes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentGuides(int $limit = 10): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.status = :status')
            ->setParameter('status', 'approved')
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchGuides(string $query): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.status = :status')
            ->andWhere('g.title LIKE :query OR g.description LIKE :query')
            ->setParameter('status', 'approved')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
