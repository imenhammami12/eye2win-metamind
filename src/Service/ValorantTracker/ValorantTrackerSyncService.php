<?php

namespace App\Service\ValorantTracker;

use App\Entity\EquipeValorant;
use App\Entity\JoueurValorant;
use App\Entity\MatchValorant;
use App\Entity\StatistiqueValorant;
use App\Entity\User;
use App\Repository\MatchValorantRepository;
use Doctrine\ORM\EntityManagerInterface;

class ValorantTrackerSyncService
{
    public function __construct(
        private readonly TrackerGgClient $trackerGgClient,
        private readonly MatchValorantRepository $matchRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{imported:int,updated:int}
     */
    public function syncMatches(User $owner, string $riotName, string $riotTag, string $region = 'eu', ?string $selectedMatchId = null): array
    {
        $matches = $this->trackerGgClient->fetchMatches($riotName, $riotTag, $region);

        if ($selectedMatchId !== null && $selectedMatchId !== '') {
            $matches = array_values(array_filter(
                $matches,
                static fn (array $match): bool => ($match['trackerMatchId'] ?? '') === $selectedMatchId
            ));
        }

        $imported = 0;
        $updated = 0;

        foreach ($matches as $row) {
            $trackerMatchId = (string) ($row['trackerMatchId'] ?? '');
            if ($trackerMatchId === '') {
                continue;
            }

            $match = $this->matchRepository->findOneBy(['owner' => $owner, 'trackerMatchId' => $trackerMatchId]);
            $isNew = !$match instanceof MatchValorant;

            if ($isNew) {
                $match = (new MatchValorant())
                    ->setOwner($owner)
                    ->setTrackerMatchId($trackerMatchId)
                    ->setCreatedAt(new \DateTimeImmutable());
            }

            $playedAt = null;
            if (!empty($row['playedAt'])) {
                try {
                    $playedAt = new \DateTimeImmutable((string) $row['playedAt']);
                } catch (\Throwable) {
                    $playedAt = null;
                }
            }

            $match
                ->setMapName($row['mapName'] ?: null)
                ->setMode($row['mode'] ?: null)
                ->setPlayedAt($playedAt)
                ->setDurationSeconds($row['durationSeconds'] ?? null)
                ->setRawData(is_array($row['raw'] ?? null) ? $row['raw'] : null)
                ->setUpdatedAt(new \DateTimeImmutable())
                ->setArchivedAt(null);

            foreach ($match->getEquipes()->toArray() as $existingTeam) {
                $match->removeEquipe($existingTeam);
            }
            foreach ($match->getJoueurs()->toArray() as $existingPlayer) {
                $match->removeJoueur($existingPlayer);
            }

            $teamMap = [];
            foreach (($row['teams'] ?? []) as $index => $teamData) {
                $team = (new EquipeValorant())
                    ->setName((string) ($teamData['name'] ?? ('Team ' . ($index + 1))))
                    ->setSide(($teamData['side'] ?? null) ?: null)
                    ->setScore(isset($teamData['score']) ? (int) $teamData['score'] : null);
                $match->addEquipe($team);
                $teamMap[$team->getName()] = $team;

                if ($index === 0) {
                    $match->setScoreTeamA($team->getScore());
                }
                if ($index === 1) {
                    $match->setScoreTeamB($team->getScore());
                }
            }

            foreach (($row['players'] ?? []) as $playerData) {
                $player = (new JoueurValorant())
                    ->setMatch($match)
                    ->setRiotName((string) ($playerData['riotName'] ?? 'Unknown'))
                    ->setRiotTag(($playerData['riotTag'] ?? null) ?: null)
                    ->setTrackerPlayerId(($playerData['trackerPlayerId'] ?? null) ?: null)
                    ->setAgent(($playerData['agent'] ?? null) ?: null);

                $teamName = (string) ($playerData['teamName'] ?? '');
                if ($teamName !== '' && isset($teamMap[$teamName])) {
                    $player->setEquipe($teamMap[$teamName]);
                }

                $stats = $playerData['stats'] ?? [];
                $statEntity = (new StatistiqueValorant())
                    ->setKills((int) ($stats['kills'] ?? 0))
                    ->setDeaths((int) ($stats['deaths'] ?? 0))
                    ->setAssists((int) ($stats['assists'] ?? 0))
                    ->setHeadshots(isset($stats['headshots']) ? (int) $stats['headshots'] : null)
                    ->setDamage(isset($stats['damage']) ? (int) $stats['damage'] : null)
                    ->setWeapons(is_array($playerData['weapons'] ?? null) ? $playerData['weapons'] : null)
                    ->setTimings(is_array($playerData['timings'] ?? null) ? $playerData['timings'] : null)
                    ->setExtra(is_array($playerData['extra'] ?? null) ? $playerData['extra'] : null);

                $player->setStatistique($statEntity);
                $match->addJoueur($player);
            }

            $this->entityManager->persist($match);

            if ($isNew) {
                $imported++;
            } else {
                $updated++;
            }
        }

        $this->entityManager->flush();

        return ['imported' => $imported, 'updated' => $updated];
    }

    public function archive(MatchValorant $match): void
    {
        $match->setArchivedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function delete(MatchValorant $match): void
    {
        $this->entityManager->remove($match);
        $this->entityManager->flush();
    }
}
