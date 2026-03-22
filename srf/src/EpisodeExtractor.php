<?php

declare(strict_types=1);

/**
 * Walks SRG JSON (Video API / IL) and collects mediaList-style nodes with episode + subtitlesAvailable.
 */
final class EpisodeExtractor
{
    /**
     * @return list<array{episode_id: string, title: ?string, description: ?string, permalink: ?string, published: ?string, subtitles_available: bool, raw: array<string, mixed>}>
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
                $sub = !empty($node['subtitlesAvailable']);
                $title = self::pickString($episode, ['title', 'name', 'shortTitle']);
                $desc = self::pickString($episode, ['lead', 'description', 'shortDescription']);
                $link = self::pickString($episode, ['playableUrl', 'url', 'shareUrl', 'link']);
                $published = self::pickString($episode, ['publishedDate', 'publishDate', 'date', 'createdDate']);
                $out[] = [
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
