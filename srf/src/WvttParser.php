<?php

declare(strict_types=1);

final class WvttParser
{
    public static function toPlainText(string $vtt): string
    {
        $lines = preg_split('/\R/', $vtt) ?: [];
        $buf = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'WEBVTT' || preg_match('/^NOTE\b/', $line)) {
                continue;
            }
            if (preg_match('/^\d{2}:\d{2}:\d{2}/', $line) || preg_match('/^align:/', $line)) {
                continue;
            }
            if (preg_match('/^Kind:|^Language:/', $line)) {
                continue;
            }
            $buf[] = $line;
        }
        $text = implode(' ', $buf);
        $text = preg_replace('/\s+/', ' ', $text) ?: '';
        return trim($text);
    }
}
