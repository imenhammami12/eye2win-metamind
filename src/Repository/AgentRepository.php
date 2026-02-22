<?php

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agent>
 *
 * @method Agent|null find($id, $lockMode = null, $lockVersion = null)
 * @method Agent|null findOneBy(array $criteria, array $orderBy = null)
 * @method Agent[]    findAll()
 * @method Agent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    public function findByGameAndSlug(Game $game, string $slug): ?Agent
    {
        return $this->findOneBy(['game' => $game, 'slug' => $slug]);
    }

    public function findByGame(Game $game): array
    {
        return $this->findBy(['game' => $game], ['name' => 'ASC']);
    }
}
