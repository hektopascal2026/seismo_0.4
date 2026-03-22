<?php

declare(strict_types=1);

/**
 * Walks SRG JSON (Video API / IL) and collects mediaList-style nodes with episode + subtitlesAvailable.
 */
final class EpisodeExtractor
{
    /**
     * @return list<array{bu: string, episode_id: string, title: ?string, description: ?string, permalink: ?string, published: ?string, subtitles_available: bool, raw: array<string, mixed>}>
     */
    public static function fromDecodedJson($data, string $bu): array
    {
        $out = [];
        self::walk($data, $bu, $out);
        return $out;
    }

    /**
     * @param mixed $node
     * @param list<array<string, mixed>> $out
     */
    private static function walk($node, string $bu, array &$out): void
    {
        if (!is_array($node)) {
            return;
        }

        if (self::isMediaNode($node)) {
            $episode = $node['episode'] ?? null;
            if (is_array($episode) && !empty($episode['id'])) {
                $id = (string) $episode['id'];
                $sub = self::hasSubtitleHint($node, $episode);
                $title = self::pickString($episode, ['title', 'name', 'shortTitle']);
                $desc = self::pickString($episode, ['lead', 'description', 'shortDescription']);
                $link = self::pickString($episode, ['playableUrl', 'url', 'shareUrl', 'link']);
                $published = self::pickString($episode, ['publishedDate', 'publishDate', 'date', 'createdDate']);
                $buSlug = self::buForMedia($node, $bu);
                $out[] = [
                    'bu' => $buSlug,
                    'episode_id' => $id,
                    'title' => $title,
                    'description' => $desc,
                    'permalink' => $link,
                    'published' => $published,
                    'subtitles_available' => $sub,
                    'raw' => $node,
                ];
            }
        }

        $isAssoc = array_keys($node) !== range(0, count($node) - 1);
        if ($isAssoc) {
            foreach ($node as $v) {
                self::walk($v, $bu, $out);
            }
        } else {
            foreach ($node as $v) {
                self::walk($v, $bu, $out);
            }
        }
    }

    /** @param array<string, mixed> $node */
    private static function isMediaNode(array $node): bool
    {
        return isset($node['episode']) && is_array($node['episode']);
    }

    /**
     * Play Subtitles URNs use the media vendor (srf, rts, …), not only the API ?bu= filter.
     *
     * @param array<string, mixed> $node Media item from Video API
     */
    private static function buForMedia(array $node, string $fallback): string
    {
        $v = $node['vendor'] ?? null;
        if (is_string($v) && $v !== '') {
            return strtolower($v);
        }
        if (is_array($v)) {
            foreach (['value', 'name', 'code'] as $k) {
                if (!empty($v[$k]) && is_string($v[$k])) {
                    return strtolower($v[$k]);
                }
            }
        }
        return strtolower($fallback);
    }

    /**
     * @param array<string, mixed> $node Media / mediaList item
     * @param array<string, mixed> $episode
     */
    private static function hasSubtitleHint(array $node, array $episode): bool
    {
        if (!empty($node['subtitlesAvailable']) || !empty($node['subtitles_available'])) {
            return true;
        }
        if (!empty($episode['subtitlesAvailable']) || !empty($episode['subtitles_available'])) {
            return true;
        }
        if (isset($node['subtitleList']) && is_array($node['subtitleList']) && $node['subtitleList'] !== []) {
            return true;
        }
        if (isset($episode['subtitleList']) && is_array($episode['subtitleList']) && $episode['subtitleList'] !== []) {
            return true;
        }
        return false;
    }

    /**
     * @param array<string, mixed> $arr
     * @param list<string> $keys
     */
    private static function pickString(array $arr, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (!empty($arr[$k]) && is_string($arr[$k])) {
                return $arr[$k];
            }
        }
        return null;
    }

    public static function buildEpisodeUrn(string $bu, string $episodeId): string
    {
        return 'urn:' . $bu . ':episode:tv:' . $episodeId;
    }
}
