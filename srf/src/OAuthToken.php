<?php

declare(strict_types=1);

final class SrgOAuthToken
{
    /** @var array{access_token: string, expires_at: int}|null */
    private static $cache = null;

    /**
     * @param array<string, mixed> $cfg
     */
    public static function getBearer(array $cfg, callable $stateGet, callable $stateSet): string
    {
        $now = time();
        if (self::$cache !== null && self::$cache['expires_at'] > $now + 30) {
            return self::$cache['access_token'];
        }
        $raw = $stateGet('oauth_token');
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j) && !empty($j['access_token']) && !empty($j['expires_at']) && (int) $j['expires_at'] > $now + 30) {
                self::$cache = ['access_token' => (string) $j['access_token'], 'expires_at' => (int) $j['expires_at']];
                return self::$cache['access_token'];
            }
        }

        $key = self::cleanCredential(SRG_CONSUMER_KEY);
        $secret = self::cleanCredential(SRG_CONSUMER_SECRET);
        if ($key === '' || $secret === '') {
            throw new RuntimeException('SRG OAuth: empty consumer key or secret — check srf/config.local.php');
        }

        $r = self::requestAccessToken($cfg, $key, $secret);
        if ($r['code'] < 200 || $r['code'] >= 300) {
            throw new RuntimeException(
                'SRG OAuth failed HTTP ' . $r['code'] . ': ' . mb_substr($r['body'], 0, 500)
                . ' — Keys are rejected by SRG (wrong values, Pending app, or missing API product).'
                . ' Run: php srf/bin/oauth_probe.php (on the server) and send output to api@srgssr.ch.'
                . ' Portal: Consumer Key + Consumer Secret; SRG_API_KEY if listed; all products Approved.'
            );
        }
        $data = json_decode($r['body'], true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('SRG OAuth: no access_token in response: ' . mb_substr($r['body'], 0, 300));
        }
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
        $expiresAt = $now + max(60, $expiresIn - 120);
        $payload = json_encode(['access_token' => $data['access_token'], 'expires_at' => $expiresAt]);
        $stateSet('oauth_token', $payload);
        self::$cache = ['access_token' => $data['access_token'], 'expires_at' => $expiresAt];
        return self::$cache['access_token'];
    }

    private static function cleanCredential(string $v): string
    {
        $v = trim($v);
        if ($v !== '' && strncmp($v, "\xEF\xBB\xBF", 3) === 0) {
            $v = substr($v, 3);
        }
        return trim($v);
    }

    /**
     * Video OpenAPI tokenUrl is https://srgssr-prod.apigee.net/oauth/v1/accesstoken — keys may only work there.
     * Play Subtitles references api.srgssr.ch; we try both bases with the same patterns.
     *
     * @param array<string, mixed> $cfg
     * @return array{body: string, code: int, content_type: string}
     */
    private static function requestAccessToken(array $cfg, string $key, string $secret): array
    {
        $configured = $cfg['oauth_token_url'] ?? 'https://srgssr-prod.apigee.net/oauth/v1/accesstoken?grant_type=client_credentials';
        $basic = base64_encode($key . ':' . $secret);
        $apiKey = SRG_API_KEY !== '' ? self::cleanCredential(SRG_API_KEY) : '';

        $headersBasic = [
            'Authorization' => 'Basic ' . $basic,
            'Cache-Control' => 'no-cache',
        ];

        $bases = [];
        $fromCfg = self::oauthBaseUrl($configured);
        if ($fromCfg !== '') {
            $bases[] = $fromCfg;
        }
        foreach ([
            'https://srgssr-prod.apigee.net/oauth/v1/accesstoken',
            'https://api.srgssr.ch/oauth/v1/accesstoken',
        ] as $b) {
            if (!in_array($b, $bases, true)) {
                $bases[] = $b;
            }
        }

        $last = ['body' => '', 'code' => 0, 'content_type' => ''];
        foreach ($bases as $base) {
            $uForm = $base;
            if ($apiKey !== '') {
                $uForm .= '?apikey=' . rawurlencode($apiKey);
            }
            $last = SrfHttp::postRaw(
                $uForm,
                'grant_type=client_credentials',
                $headersBasic + ['Content-Type' => 'application/x-www-form-urlencoded'],
                false
            );
            if (self::isTokenSuccess($last)) {
                return $last;
            }

            $uQuery = $base . '?grant_type=client_credentials';
            if ($apiKey !== '') {
                $uQuery .= '&apikey=' . rawurlencode($apiKey);
            }
            $last = SrfHttp::postEmpty($uQuery, $headersBasic, false);
            if (self::isTokenSuccess($last)) {
                return $last;
            }

            if ($apiKey !== '') {
                $last = SrfHttp::postRaw(
                    $base,
                    'grant_type=client_credentials',
                    $headersBasic + [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'x-api-key' => $apiKey,
                    ],
                    false
                );
                if (self::isTokenSuccess($last)) {
                    return $last;
                }
            }

            // 4) client_id + client_secret in body only (no Basic) — some Apigee policies.
            $uBody = $base;
            if ($apiKey !== '') {
                $uBody .= '?apikey=' . rawurlencode($apiKey);
            }
            $bodyCreds = http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $key,
                'client_secret' => $secret,
            ], '', '&', PHP_QUERY_RFC3986);
            $last = SrfHttp::postRaw(
                $uBody,
                $bodyCreds,
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Cache-Control' => 'no-cache',
                ],
                false
            );
            if (self::isTokenSuccess($last)) {
                return $last;
            }
        }

        return $last;
    }

    /**
     * @param array{body: string, code: int, content_type: string} $r
     */
    private static function isTokenSuccess(array $r): bool
    {
        if ($r['code'] < 200 || $r['code'] >= 300) {
            return false;
        }
        $data = json_decode($r['body'], true);
        return is_array($data) && !empty($data['access_token']);
    }

    private static function oauthBaseUrl(string $configured): string
    {
        $p = parse_url($configured);
        if (!is_array($p) || empty($p['host'])) {
            return '';
        }
        $scheme = $p['scheme'] ?? 'https';
        $path = $p['path'] ?? '/oauth/v1/accesstoken';
        return $scheme . '://' . $p['host'] . $path;
    }
}
