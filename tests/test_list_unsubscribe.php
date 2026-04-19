<?php
/**
 * CLI sanity check for esParseListUnsubscribe().
 * Run: php tests/test_list_unsubscribe.php
 */
require_once __DIR__ . '/../controllers/email_subscriptions.php';

function assert_same(string $label, $a, $b): void {
    if ($a === $b) {
        echo "OK  {$label}\n";
        return;
    }
    echo "FAIL {$label} — expected " . var_export($b, true) . " got " . var_export($a, true) . "\n";
    exit(1);
}

$hdrs = "List-Unsubscribe: <https://example.com/unsub>, <mailto:unsub@example.com>\r\n";
$r = esParseListUnsubscribe($hdrs);
assert_same('url', $r['url'], 'https://example.com/unsub');
assert_same('mailto', $r['mailto'], 'mailto:unsub@example.com');
assert_same('one_click false', $r['one_click'], false);

$hdrs2 = "List-Unsubscribe: <https://a.example.com/x>\r\n List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
$r2 = esParseListUnsubscribe($hdrs2);
assert_same('one_click true', $r2['one_click'], true);

$folded = "List-Unsubscribe:\r\n <https://b.example.com/u>,\r\n <mailto:x@y.com>\r\n";
$r3 = esParseListUnsubscribe($folded);
assert_same('folded url', $r3['url'], 'https://b.example.com/u');

echo "All list-unsubscribe tests passed.\n";
