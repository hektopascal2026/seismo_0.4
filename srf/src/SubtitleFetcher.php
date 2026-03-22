<?php

declare(strict_types=1);

final class SubtitleFetcher
{
    /**
     * @param array<string, mixed> $cfg
     * @return array{text: string, lang: ?string}
     */
    public static function fetchForUrn(string $bearer, array $cfg, string $urn): array
    {
        $template = $cfg['subtitle_lookup_url_template'] ?? '';
        if ($template === '') {
            return ['text' => '', 'lang' => null];
        }
        $url = str_replace('{urn}', rawurlencode($urn), $template);
        $url = self::appendApiKey($url);

        $r = SrfHttp::request('GET', $url, [
            'Authorization' => 'Bearer ' . $bearer,
            'Accept' => 'application/json, text/vtt, text/plain, */*',
        ]);

        if ($r['code'] < 200 || $r['code'] >= 300) {
            throw new RuntimeException('Subtitle lookup HTTP ' . $r['code'] . ' for ' . $urn);
        }

        $body = $r['body'];
        $ct = strtolower($r['content_type']);

        if (strpos($ct, 'json') !== false || (strlen($body) > 0 && ($body[0] === '{' || $body[0] === '['))) {
            $j = json_decode($body, true);
            $vttUrl = self::firstSubtitleUrl($j);
            if ($vttUrl === null) {
                return ['text' => '', 'lang' => null];
            }
            $vttUrl = self::appendApiKey($vttUrl);
            $r2 = SrfHttp::request('GET', $vttUrl, [
                'Authorization' => 'Bearer ' . $bearer,
                'Accept' => 'text/vtt, text/plain, */*',
            ]);
            if ($r2['code'] < 200 || $r2['code'] >= 300) {
                throw new RuntimeException('Subtitle file HTTP ' . $r2['code']);
            }
            $body = $r2['body'];
        }

        $lang = self::guessLangFromVtt($body);
        return ['text' => WvttParser::toPlainText($body), 'lang' => $lang];
    }

    private static function appendApiKey(string $url): string
    {
        if (SRG_API_KEY === '') {
            return $url;
        }
        $sep = strpos($url, '?') !== false ? '&' : '?';
        return $url . $sep . 'apikey=' . rawurlencode(SRG_API_KEY);
    }

    /**
     * @param mixed $node
     */
    private static function firstSubtitleUrl($node): ?string
    {
        if (is_string($node)) {
            if (preg_match('#https?://[^\s"\'<>]+\.(vtt|webvtt)(\\?[^\s"\'<>]*)?#i', $node, $m)) {
                return $m[0];
            }
            return null;
        }
        if (!is_array($node)) {
            return null;
        }
        foreach (['subtitleUrl', 'subtitle', 'url', 'fileUrl', 'src', 'uri', 'link'] as $k) {
            if (!empty($node[$k]) && is_string($node[$k])) {
                $u = $node[$k];
                if (preg_match('#^https?://#i', $u)) {
                    return $u;
                }
            }
        }
        foreach ($node as $v) {
            $f = self::firstSubtitleUrl($v);
            if ($f !== null) {
                return $f;
            }
        }
        return null;
    }

    private static function guessLangFromVtt(string $vtt): ?string
    {
        if (preg_match('/^Language:\s*(\S+)/mi', $vtt, $m)) {
            return strtolower($m[1]);
        }
        return null;
    }
}
