<?php

declare(strict_types=1);

/**
 * Limits Play Subtitles API calls using optional allowlists and regex on titles (SRG does not expose a stable "genre" field).
 */
final class SubtitleFetchFilter
{
    /**
     * @param array<string, mixed> $row srf_items row
     * @param array<string, mixed> $cfg merged config
     */
    public static function shouldFetchSubtitles(array $row, array $cfg): bool
    {
        $f = $cfg['subtitle_filters'] ?? null;
        if (!is_array($f) || $f === []) {
            return true;
        }

        $showIds = self::stringList($f['allow_show_ids'] ?? []);
        $chanIds = self::stringList($f['allow_channel_ids'] ?? []);
        $must = trim((string) ($f['title_must_match_regex'] ?? ''));
        $not = trim((string) ($f['title_must_not_match_regex'] ?? ''));

        if ($showIds === [] && $chanIds === [] && $must === '' && $not === '') {
            return true;
        }

        $haystack = implode("\n", array_filter([
            (string) ($row['title'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['show_title'] ?? ''),
            (string) ($row['channel_title'] ?? ''),
        ], static function ($s) {
            return $s !== '';
        }));

        if ($must !== '') {
            if (@preg_match($must, $haystack) !== 1) {
                return false;
            }
        }
        if ($not !== '') {
            if (@preg_match($not, $haystack) === 1) {
                return false;
            }
        }

        $needShow = $showIds !== [];
        $needChan = $chanIds !== [];
        if (!$needShow && !$needChan) {
            return true;
        }

        $sid = isset($row['show_id']) ? (string) $row['show_id'] : '';
        $cid = isset($row['channel_id']) ? (string) $row['channel_id'] : '';
        $okShow = $sid !== '' && in_array($sid, $showIds, true);
        $okChan = $cid !== '' && in_array($cid, $chanIds, true);

        if ($needShow && $needChan) {
            return $okShow || $okChan;
        }
        if ($needShow) {
            return $okShow;
        }
        return $okChan;
    }

    /**
     * @param mixed $v
     * @return list<string>
     */
    private static function stringList($v): array
    {
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $x) {
            $s = trim((string) $x);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }
}
