<?php

namespace App\Service\ValorantTracker;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TrackerGgClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey,
        private readonly string $baseUrl
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchMatches(string $riotName, string $riotTag, string $region = 'eu'): array
    {
        if (($this->apiKey ?? '') === '') {
            throw new \RuntimeException('TRACKER_GG_API_KEY manquant. Configurez votre clé API Tracker.gg dans .env.local.');
        }

        $url = rtrim($this->baseUrl, '/') . '/valorant/standard/matches/riot/' . rawurlencode($riotName) . '/' . rawurlencode($riotTag);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'TRN-Api-Key' => (string) $this->apiKey,
                    'Accept' => 'application/json',
                    'User-Agent' => 'Eye2WinTracker/1.0 (+Symfony)',
                ],
                'query' => ['region' => $region],
            ]);

            if ($response->getStatusCode() >= 400) {
                $statusCode = $response->getStatusCode();
                $rawBody = $response->getContent(false);
                $headers = $response->getHeaders(false);
                $serverHeader = strtolower((string) (($headers['server'][0] ?? '')));
                $cfRay = (string) ($headers['cf-ray'][0] ?? '');

                if ($statusCode === 403) {
                    $cloudflareBlocked = stripos($rawBody, 'Attention Required') !== false
                        || stripos($rawBody, 'cloudflare') !== false
                        || str_contains($serverHeader, 'cloudflare')
                        || $cfRay !== '';

                    if ($cloudflareBlocked) {
                        $raySuffix = $cfRay !== '' ? (' (CF-Ray: ' . $cfRay . ')') : '';
                        throw new \RuntimeException('Tracker.gg refuse la requête (HTTP 403 / Cloudflare). Vérifiez la validité de la clé API Tracker, puis réessayez depuis un autre réseau/IP (VPN ou serveur) car votre IP semble bloquée par le WAF.' . $raySuffix);
                    }

                    throw new \RuntimeException('Tracker.gg refuse la requête (HTTP 403). Vérifiez que la clé API Tracker.gg est valide et autorisée pour l\'endpoint Valorant.');
                }

                throw new \RuntimeException('Tracker.gg indisponible (HTTP ' . $statusCode . ').');
            }

            $payload = $response->toArray(false);
            $matches = $payload['data']['matches'] ?? $payload['data'] ?? $payload['matches'] ?? [];

            if (!is_array($matches)) {
                return [];
            }

            return array_values(array_filter(array_map(fn (mixed $match): array => $this->normalizeMatch($match), $matches)));
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('Impossible de contacter Tracker.gg: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function normalizeMatch(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $metadata = is_array($raw['metadata'] ?? null) ? $raw['metadata'] : [];
        $segments = is_array($raw['segments'] ?? null) ? $raw['segments'] : [];
        $attributes = is_array($raw['attributes'] ?? null) ? $raw['attributes'] : [];

        $id = (string) ($metadata['matchId'] ?? $raw['id'] ?? $attributes['id'] ?? '');
        if ($id === '') {
            return [];
        }

        $teams = [];
        $players = [];

        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $type = (string) ($segment['type'] ?? '');
            $stats = is_array($segment['stats'] ?? null) ? $segment['stats'] : [];
            $meta = is_array($segment['metadata'] ?? null) ? $segment['metadata'] : [];

            if ($type === 'team') {
                $teams[] = [
                    'name' => (string) ($meta['name'] ?? 'Team'),
                    'side' => (string) ($meta['side'] ?? ''),
                    'score' => $this->statValue($stats['roundsWon'] ?? $stats['score'] ?? null),
                ];
            }

            if ($type === 'player') {
                $players[] = [
                    'trackerPlayerId' => (string) ($meta['platformUserIdentifier'] ?? $meta['platformUserId'] ?? ''),
                    'riotName' => (string) ($meta['platformUserHandle'] ?? $meta['name'] ?? 'Unknown'),
                    'riotTag' => (string) ($meta['tag'] ?? ''),
                    'teamName' => (string) ($meta['teamName'] ?? ''),
                    'agent' => (string) ($meta['agentName'] ?? ''),
                    'stats' => [
                        'kills' => $this->statValue($stats['kills'] ?? null),
                        'deaths' => $this->statValue($stats['deaths'] ?? null),
                        'assists' => $this->statValue($stats['assists'] ?? null),
                        'headshots' => $this->statValue($stats['headshots'] ?? null),
                        'damage' => $this->statValue($stats['damage'] ?? null),
                    ],
                    'weapons' => is_array($segment['weapons'] ?? null) ? $segment['weapons'] : null,
                    'timings' => is_array($segment['timings'] ?? null) ? $segment['timings'] : null,
                    'extra' => $stats,
                ];
            }
        }

        return [
            'trackerMatchId' => $id,
            'mapName' => (string) ($metadata['mapName'] ?? $metadata['map'] ?? ''),
            'mode' => (string) ($metadata['modeName'] ?? $metadata['mode'] ?? ''),
            'playedAt' => (string) ($metadata['timestamp'] ?? $metadata['playedAt'] ?? ''),
            'durationSeconds' => $this->statValue($metadata['duration'] ?? null),
            'teams' => $teams,
            'players' => $players,
            'raw' => $raw,
        ];
    }

    private function statValue(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = $value['value'] ?? $value['displayValue'] ?? null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
