<?php

declare(strict_types=1);

final class SrfHttp
{
    /** @return array{body: string, code: int, content_type: string} */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $ch = curl_init($url);
        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'Seismo-SRF-Monitor/1.0',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($resp === false) {
            return ['body' => '', 'code' => 0, 'content_type' => $ct];
        }
        return ['body' => $resp, 'code' => $code, 'content_type' => $ct];
    }

    /**
     * @return array{body: string, code: int, content_type: string}
     */
    public static function postEmpty(string $url, array $headers = [], bool $followRedirects = true): array
    {
        $ch = curl_init($url);
        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => $followRedirects ? 5 : 0,
            CURLOPT_USERAGENT => 'Seismo-SRF-Monitor/1.0',
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($resp === false) {
            return ['body' => '', 'code' => 0, 'content_type' => $ct];
        }
        return ['body' => $resp, 'code' => $code, 'content_type' => $ct];
    }

    /**
     * POST with raw body (e.g. application/x-www-form-urlencoded).
     *
     * @return array{body: string, code: int, content_type: string}
     */
    public static function postRaw(string $url, string $body, array $headers = [], bool $followRedirects = false): array
    {
        $ch = curl_init($url);
        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => $followRedirects ? 5 : 0,
            CURLOPT_USERAGENT => 'Seismo-SRF-Monitor/1.0',
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($resp === false) {
            return ['body' => '', 'code' => 0, 'content_type' => $ct];
        }
        return ['body' => $resp, 'code' => $code, 'content_type' => $ct];
    }
}
