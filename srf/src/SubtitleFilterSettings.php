<?php

declare(strict_types=1);

/**
 * Parse and format subtitle_filters for the web settings UI (stored JSON in srf_fetch_state).
 */
final class SubtitleFilterSettings
{
    public const STATE_KEY = 'subtitle_filters_json';

    /**
     * @param array<string, mixed> $filters
     * @return array{allow_show_ids: string, allow_channel_ids: string, title_must_match_regex: string, title_must_not_match_regex: string}
     */
    public static function toForm(array $filters): array
    {
        $shows = $filters['allow_show_ids'] ?? [];
        if (!is_array($shows)) {
            $shows = [];
        }
        $chans = $filters['allow_channel_ids'] ?? [];
        if (!is_array($chans)) {
            $chans = [];
        }
        return [
            'allow_show_ids' => implode("\n", array_map('strval', $shows)),
            'allow_channel_ids' => implode("\n", array_map('strval', $chans)),
            'title_must_match_regex' => (string) ($filters['title_must_match_regex'] ?? ''),
            'title_must_not_match_regex' => (string) ($filters['title_must_not_match_regex'] ?? ''),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    public static function parseFromRequest(array $post): array
    {
        $errors = [];
        $filters = [];

        $shows = self::splitIds((string) ($post['allow_show_ids'] ?? ''));
        if ($shows !== []) {
            $filters['allow_show_ids'] = $shows;
        }

        $chans = self::splitIds((string) ($post['allow_channel_ids'] ?? ''));
        if ($chans !== []) {
            $filters['allow_channel_ids'] = $chans;
        }

        $must = trim((string) ($post['title_must_match_regex'] ?? ''));
        if ($must !== '') {
            if (!self::regexValid($must)) {
                $errors[] = '"Must match" regex is not valid PCRE.';
            } else {
                $filters['title_must_match_regex'] = $must;
            }
        }

        $not = trim((string) ($post['title_must_not_match_regex'] ?? ''));
        if ($not !== '') {
            if (!self::regexValid($not)) {
                $errors[] = '"Must not match" regex is not valid PCRE.';
            } else {
                $filters['title_must_not_match_regex'] = $not;
            }
        }

        return [$filters, $errors];
    }

    private static function regexValid(string $pattern): bool
    {
        return @preg_match($pattern, '') !== false;
    }

    /**
     * @return list<string>
     */
    private static function splitIds(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }
        return array_values(array_unique(array_map('trim', $parts)));
    }
}
