<?php

declare(strict_types=1);

final class FetchService
{
    private ItemRepository $repo;
    /** @var array<string, mixed> */
    private array $cfg;

    /**
     * @param array<string, mixed> $cfg merged config.json + defaults
     */
    public function __construct(ItemRepository $repo, array $cfg)
    {
        $this->repo = $repo;
        $this->cfg = $cfg;
    }

    /**
     * @return array{episodes_seen: int, subtitles_fetched: int, subtitles_attempted: int, errors: list<string>}
     */
    public function run(bool $fetchSubtitles, int $subtitleBatch, int $msDelay): array
    {
        $errors = [];
        if (!srf_srg_configured()) {
            return ['episodes_seen' => 0, 'subtitles_fetched' => 0, 'subtitles_attempted' => 0, 'errors' => ['SRG_CONSUMER_KEY / SRG_CONSUMER_SECRET not set in config.local.php']];
        }

        $bu = (string) ($this->cfg['bu'] ?? 'srf');
        $pageSize = min(100, max(1, (int) ($this->cfg['page_size'] ?? 30)));

        $bearer = SrgOAuthToken::getBearer(
            $this->cfg,
            fn (string $k) => $this->repo->stateGet($k),
            fn (string $k, string $v) => $this->repo->stateSet($k, $v)
        );

        $listUrl = $this->buildListUrl($bu, $pageSize);
        $listUrl = $this->appendApiKey($listUrl);

        $r = SrfHttp::request('GET', $listUrl, [
            'Authorization' => 'Bearer ' . $bearer,
            'Accept' => 'application/json',
        ]);

        if ($r['code'] < 200 || $r['code'] >= 300) {
            $errors[] = 'Video list HTTP ' . $r['code'] . ': ' . mb_substr($r['body'], 0, 400);
            return ['episodes_seen' => 0, 'subtitles_fetched' => 0, 'subtitles_attempted' => 0, 'errors' => $errors];
        }

        $json = json_decode($r['body'], true);
        if (!is_array($json)) {
            $errors[] = 'Video list: invalid JSON';
            return ['episodes_seen' => 0, 'subtitles_fetched' => 0, 'subtitles_attempted' => 0, 'errors' => $errors];
        }

        $episodes = EpisodeExtractor::fromDecodedJson($json, $bu);
        $seen = 0;
        foreach ($episodes as $ep) {
            $buSlug = (string) ($ep['bu'] ?? $bu);
            $urn = EpisodeExtractor::buildEpisodeUrn($buSlug, $ep['episode_id']);
            $published = self::normalizeDate($ep['published']);
            $this->repo->upsertItem([
                'urn' => $urn,
                'bu' => $buSlug,
                'episode_id' => $ep['episode_id'],
                'title' => $ep['title'],
                'description' => $ep['description'],
                'permalink' => $ep['permalink'],
                'published_at' => $published,
                'subtitles_available' => $ep['subtitles_available'],
                'raw_metadata' => $ep['raw'],
            ]);
            $seen++;
        }

        $subCount = 0;
        $subAttempted = 0;
        if ($fetchSubtitles && $subtitleBatch > 0) {
            $pending = $this->repo->itemsNeedingSubtitles($subtitleBatch);
            foreach ($pending as $row) {
                $urn = (string) $row['urn'];
                $subAttempted++;
                if ($msDelay > 0) {
                    usleep($msDelay * 1000);
                }
                try {
                    $result = SubtitleFetcher::fetchForUrn($bearer, $this->cfg, $urn);
                    $updated = $this->repo->updateSubtitleText($urn, $result['text'], $result['lang']);
                    if ($updated === 0) {
                        $errors[] = $urn . ': subtitle UPDATE matched 0 rows (check urn in DB)';
                    }
                    if ($result['text'] !== '') {
                        $subCount++;
                    }
                } catch (Throwable $e) {
                    $errors[] = $urn . ': ' . $e->getMessage();
                }
            }
        }

        $this->repo->stateSet('last_list_sync', date('c'));

        return [
            'episodes_seen' => $seen,
            'subtitles_fetched' => $subCount,
            'subtitles_attempted' => $subAttempted,
            'errors' => $errors,
        ];
    }

    private function buildListUrl(string $bu, int $pageSize): string
    {
        $query = ['bu' => $bu, 'pageSize' => $pageSize];
        if (!empty($this->cfg['use_episodes_by_date'])) {
            $base = (string) ($this->cfg['video_episodes_by_date_url'] ?? '');
            $day = (new DateTimeImmutable('today'))->format('Y-m-d');
            $base = str_replace('{day}', $day, $base);
            $q = http_build_query($query);
            return $base . (strpos($base, '?') !== false ? '&' : '?') . $q;
        }
        $base = (string) ($this->cfg['video_latest_episodes_url'] ?? '');
        $q = http_build_query($query);
        return $base . (strpos($base, '?') !== false ? '&' : '?') . $q;
    }

    private function appendApiKey(string $url): string
    {
        if (SRG_API_KEY === '') {
            return $url;
        }
        $sep = strpos($url, '?') !== false ? '&' : '?';
        return $url . $sep . 'apikey=' . rawurlencode(SRG_API_KEY);
    }

    private static function normalizeDate(?string $s): ?string
    {
        if ($s === null || $s === '') {
            return null;
        }
        $t = strtotime($s);
        if ($t === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $t);
    }
}
