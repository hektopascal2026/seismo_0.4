<?php

declare(strict_types=1);

final class ItemRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function stateGet(string $key): ?string
    {
        $st = $this->pdo->prepare('SELECT state_value FROM srf_fetch_state WHERE state_key = ?');
        $st->execute([$key]);
        $row = $st->fetch();
        return $row ? (string) $row['state_value'] : null;
    }

    public function stateSet(string $key, string $value): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO srf_fetch_state (state_key, state_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)'
        );
        $st->execute([$key, $value]);
    }

    public function stateDelete(string $key): void
    {
        $st = $this->pdo->prepare('DELETE FROM srf_fetch_state WHERE state_key = ?');
        $st->execute([$key]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertItem(array $row): void
    {
        $sql = 'INSERT INTO srf_items (
            urn, bu, episode_id, show_id, show_title, channel_id, channel_title,
            title, description, permalink, published_at, subtitles_available, raw_metadata
        ) VALUES (
            :urn, :bu, :episode_id, :show_id, :show_title, :channel_id, :channel_title,
            :title, :description, :permalink, :published_at, :subtitles_available, :raw_metadata
        ) ON DUPLICATE KEY UPDATE
            show_id = VALUES(show_id),
            show_title = VALUES(show_title),
            channel_id = VALUES(channel_id),
            channel_title = VALUES(channel_title),
            title = VALUES(title),
            description = VALUES(description),
            permalink = VALUES(permalink),
            published_at = VALUES(published_at),
            subtitles_available = VALUES(subtitles_available),
            raw_metadata = VALUES(raw_metadata)';

        $st = $this->pdo->prepare($sql);
        $st->execute([
            'urn' => $row['urn'],
            'bu' => $row['bu'],
            'episode_id' => $row['episode_id'],
            'show_id' => $row['show_id'] ?? null,
            'show_title' => $row['show_title'] ?? null,
            'channel_id' => $row['channel_id'] ?? null,
            'channel_title' => $row['channel_title'] ?? null,
            'title' => $row['title'],
            'description' => $row['description'],
            'permalink' => $row['permalink'],
            'published_at' => $row['published_at'],
            'subtitles_available' => $row['subtitles_available'] ? 1 : 0,
            'raw_metadata' => isset($row['raw_metadata']) ? json_encode($row['raw_metadata']) : null,
        ]);
    }

    public function updateSubtitleText(string $urn, string $text, ?string $lang): int
    {
        $st = $this->pdo->prepare(
            'UPDATE srf_items SET subtitle_text = ?, subtitle_lang = ?, fetched_subtitles_at = NOW() WHERE urn = ?'
        );
        $st->execute([$text, $lang, $urn]);
        return $st->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(?string $q, int $limit, int $offset): array
    {
        if ($q !== null && trim($q) !== '') {
            $term = trim($q);
            if (mb_strlen($term) >= 2) {
                try {
                    $st = $this->pdo->prepare(
                        'SELECT * FROM srf_items
                         WHERE MATCH(title, description, subtitle_text) AGAINST (? IN NATURAL LANGUAGE MODE)
                         ORDER BY published_at DESC, id DESC
                         LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
                    );
                    $st->execute([$term]);
                    $rows = $st->fetchAll();
                    if ($rows !== []) {
                        return $rows;
                    }
                } catch (PDOException $e) {
                    // e.g. FULLTEXT missing — fall through to LIKE
                }
            }
            $likeTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $like = '%' . $likeTerm . '%';
            $st2 = $this->pdo->prepare(
                'SELECT * FROM srf_items
                 WHERE title LIKE ? OR description LIKE ? OR subtitle_text LIKE ?
                 ORDER BY published_at DESC, id DESC
                 LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
            );
            $st2->execute([$like, $like, $like]);
            return $st2->fetchAll();
        }

        $st = $this->pdo->query(
            'SELECT * FROM srf_items ORDER BY published_at DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        return $st ? $st->fetchAll() : [];
    }

    public function countSearch(?string $q): int
    {
        if ($q !== null && trim($q) !== '') {
            $term = trim($q);
            if (mb_strlen($term) >= 2) {
                try {
                    $st = $this->pdo->prepare(
                        'SELECT COUNT(*) FROM srf_items WHERE MATCH(title, description, subtitle_text) AGAINST (? IN NATURAL LANGUAGE MODE)'
                    );
                    $st->execute([$term]);
                    $n = (int) $st->fetchColumn();
                    if ($n > 0) {
                        return $n;
                    }
                } catch (PDOException $e) {
                }
            }
            $likeTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $like = '%' . $likeTerm . '%';
            $st2 = $this->pdo->prepare(
                'SELECT COUNT(*) FROM srf_items WHERE title LIKE ? OR description LIKE ? OR subtitle_text LIKE ?'
            );
            $st2->execute([$like, $like, $like]);
            return (int) $st2->fetchColumn();
        }
        $n = $this->pdo->query('SELECT COUNT(*) FROM srf_items');
        return $n ? (int) $n->fetchColumn() : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsNeedingSubtitles(int $limit): array
    {
        // latest_episodes often omits subtitlesAvailable on Media; still call Play Subtitles API once per row.
        $st = $this->pdo->prepare(
            'SELECT * FROM srf_items
             WHERE fetched_subtitles_at IS NULL
               AND (subtitle_text IS NULL OR subtitle_text = \'\')
             ORDER BY published_at DESC
             LIMIT ' . (int) $limit
        );
        $st->execute();
        return $st->fetchAll();
    }
}
