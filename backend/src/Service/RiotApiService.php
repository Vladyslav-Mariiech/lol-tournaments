<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RiotApiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'RIOT_API_KEY')]
        private readonly string $apiKey,
        private readonly LoggerInterface $logger
    ) {
    }

    private function getRegionSlug(string $region): string
    {
        return match (strtolower($region)) {
            'euw1' => 'euw',
            'eun1' => 'eune',
            'na1' => 'na',
            'tr1' => 'tr',
            'br1' => 'br',
            'la1' => 'lan',
            'la2' => 'las',
            'oc1' => 'oce',
            'jp1' => 'jp',
            default => strtolower(str_replace(['1', '2'], '', $region)),
        };
    }

    public function getPlayerData(string $riotId, string $preferredRegion = 'ru'): ?array
    {
        $parts = explode('#', trim($riotId));
        if (count($parts) !== 2) {
            return null;
        }

        [$gameName, $tagLine] = $parts;

        try {
            // 1. Account-v1
            $accountUrl = sprintf(
                'https://europe.api.riotgames.com/riot/account/v1/accounts/by-riot-id/%s/%s',
                rawurlencode($gameName),
                rawurlencode($tagLine)
            );

            $accountResponse = $this->httpClient->request('GET', $accountUrl, [
                'headers' => ['X-Riot-Token' => $this->apiKey],
            ]);

            if ($accountResponse->getStatusCode() !== 200) {
                return null;
            }

            $accountData = $accountResponse->toArray();
            $puuid = $accountData['puuid'];
            $exactGameName = $accountData['gameName'];
            $exactTagLine = $accountData['tagLine'];

            $regionsToTry = array_unique(array_merge(
                [strtolower($preferredRegion)],
                ['ru', 'euw1', 'eun1', 'tr1', 'na1', 'kr']
            ));

            $foundRegion = null;
            $soloEntry = null;

            // 2. League-v4 по PUUID
            foreach ($regionsToTry as $reg) {
                $leagueUrl = sprintf(
                    'https://%s.api.riotgames.com/lol/league/v4/entries/by-puuid/%s',
                    $reg,
                    $puuid
                );

                $leagueResponse = $this->httpClient->request('GET', $leagueUrl, [
                    'headers' => ['X-Riot-Token' => $this->apiKey],
                ]);

                if ($leagueResponse->getStatusCode() !== 200) {
                    continue;
                }

                $entries = $leagueResponse->toArray();
                if ($foundRegion === null) {
                    $foundRegion = $reg;
                }

                foreach ($entries as $entry) {
                    if (($entry['queueType'] ?? '') === 'RANKED_SOLO_5x5') {
                        $soloEntry = $entry;
                        $foundRegion = $reg;
                        break 2;
                    }
                }
            }

            if ($foundRegion === null) {
                return null;
            }

            $tier = strtoupper($soloEntry['tier'] ?? 'UNRANKED');
            $rank = $soloEntry['rank'] ?? '';
            $lp = (int)($soloEntry['leaguePoints'] ?? 0);
            $wins = (int)($soloEntry['wins'] ?? 0);
            $losses = (int)($soloEntry['losses'] ?? 0);

            $isApex = in_array($tier, ['MASTER', 'GRANDMASTER', 'CHALLENGER'], true);

            if ($tier === 'UNRANKED') {
                $rankDisplay = 'Unranked';
            } else {
                $formattedRank = $isApex ? '' : ' ' . $rank;
                $rankDisplay = sprintf(
                    '%s%s (%d LP) [%s]',
                    ucfirst(strtolower($tier)),
                    $formattedRank,
                    $lp,
                    strtoupper(str_replace(['1', '2'], '', $foundRegion))
                );
            }

            $regionSlug = $this->getRegionSlug($foundRegion);
            $userSlug = rawurlencode($exactGameName) . '-' . rawurlencode($exactTagLine);

            return [
                'gameName' => $exactGameName,
                'tagLine' => $exactTagLine,
                'fullRiotId' => $exactGameName . '#' . $exactTagLine,
                'region' => strtoupper($regionSlug),
                'rankDisplay' => $rankDisplay,
                'tier' => $tier,
                'rank' => $rank,
                'leaguePoints' => $lp,
                'wins' => $wins,
                'losses' => $losses,
                'winrate' => ($wins + $losses) > 0 ? round(($wins / ($wins + $losses)) * 100, 1) : 0,
                'externalLinks' => [
                    'opgg' => sprintf('https://op.gg/lol/summoners/%s/%s', $regionSlug, $userSlug),
                    'leagueOfGraphs' => sprintf('https://www.leagueofgraphs.com/summoner/%s/%s', $regionSlug, $userSlug),
                    'dpm' => sprintf('https://dpm.lol/%s', $userSlug),
                ]
            ];
        } catch (\Throwable $e) {
            $this->logger->error('RiotAPI Exception: ' . $e->getMessage());
            return null;
        }
    }
}
