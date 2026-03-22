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
                . ' — In the developer portal: use Consumer Key + Consumer Secret (not your login password).'
                . ' App must be Approved. Set SRG_API_KEY if the app page shows an API key.'
                . ' If it still fails, test the same request in the portal “Try it” or contact api@srgssr.ch.'
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
     * @param array<string, mixed> $cfg
     * @return array{body: string, code: int, content_type: string}
     */
    private static function requestAccessToken(array $cfg, string $key, string $secret): array
    {
        $configured = $cfg['oauth_token_url'] ?? 'https://api.srgssr.ch/oauth/v1/accesstoken?grant_type=client_credentials';
        $basic = base64_encode($key . ':' . $secret);
        $apiKey = SRG_API_KEY !== '' ? self::cleanCredential(SRG_API_KEY) : '';

        $base = self::oauthBaseUrl($configured);
        $headersBasic = [
            'Authorization' => 'Basic ' . $basic,
            'Cache-Control' => 'no-cache',
        ];

        $tries = [];

        // 1) RFC 6749: grant_type in POST body (many Apigee / OAuth2 servers expect this).
        $u1 = $base;
        if ($apiKey !== '') {
            $u1 .= (strpos($u1, '?') !== false ? '&' : '?') . 'apikey=' . rawurlencode($apiKey);
        }
        $tries[] = SrfHttp::postRaw(
            $u1,
            'grant_type=client_credentials',
            $headersBasic + [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            false
        );

        // 2) SRG portal curl example: grant_type only in query, empty POST body.
        $u2 = $configured;
        if (strpos($u2, 'grant_type=') === false) {
            $u2 .= (strpos($u2, '?') !== false ? '&' : '?') . 'grant_type=client_credentials';
        }
        if ($apiKey !== '' && stripos($u2, 'apikey=') === false) {
            $u2 .= (strpos($u2, '?') !== false ? '&' : '?') . 'apikey=' . rawurlencode($apiKey);
        }
        $tries[] = SrfHttp::postEmpty($u2, $headersBasic, false);

        // 3) Form body + x-api-key header (some gateways).
        if ($apiKey !== '') {
            $tries[] = SrfHttp::postRaw(
                $base,
                'grant_type=client_credentials',
                $headersBasic + [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'x-api-key' => $apiKey,
                ],
                false
            );
        }

        $last = $tries[0];
        foreach ($tries as $resp) {
            $last = $resp;
            if ($resp['code'] >= 200 && $resp['code'] < 300) {
                $data = json_decode($resp['body'], true);
                if (is_array($data) && !empty($data['access_token'])) {
                    return $resp;
                }
            }
        }

        return $last;
    }

    private static function oauthBaseUrl(string $configured): string
    {
        $p = parse_url($configured);
        if (!is_array($p) || empty($p['host'])) {
            return 'https://api.srgssr.ch/oauth/v1/accesstoken';
        }
        $scheme = $p['scheme'] ?? 'https';
        $path = $p['path'] ?? '/oauth/v1/accesstoken';
        return $scheme . '://' . $p['host'] . $path;
    }
}
