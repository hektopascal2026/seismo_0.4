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

        $url = $cfg['oauth_token_url'] ?? 'https://api.srgssr.ch/oauth/v1/accesstoken?grant_type=client_credentials';
        $key = trim(SRG_CONSUMER_KEY);
        $secret = trim(SRG_CONSUMER_SECRET);
        if ($key === '' || $secret === '') {
            throw new RuntimeException('SRG OAuth: empty consumer key or secret after trim — check srf/config.local.php');
        }
        if (SRG_API_KEY !== '') {
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $url .= $sep . 'apikey=' . rawurlencode(trim(SRG_API_KEY));
        }
        $basic = base64_encode($key . ':' . $secret);
        // Do not follow redirects: Authorization is often dropped on redirect → bogus 401 / empty token.
        $r = SrfHttp::postEmpty($url, [
            'Authorization' => 'Basic ' . $basic,
            'Cache-Control' => 'no-cache',
        ], false);

        if ($r['code'] < 200 || $r['code'] >= 300) {
            throw new RuntimeException(
                'SRG OAuth failed HTTP ' . $r['code'] . ': ' . mb_substr($r['body'], 0, 500)
                . ' — verify Consumer Key + Secret in the portal (no spaces), app Approved, and try SRG_API_KEY on token URL if your product requires it.'
            );
        }
        $data = json_decode($r['body'], true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('SRG OAuth: no access_token in response');
        }
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
        $expiresAt = $now + max(60, $expiresIn - 120);
        $payload = json_encode(['access_token' => $data['access_token'], 'expires_at' => $expiresAt]);
        $stateSet('oauth_token', $payload);
        self::$cache = ['access_token' => $data['access_token'], 'expires_at' => $expiresAt];
        return self::$cache['access_token'];
    }
}
