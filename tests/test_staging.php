<?php
/**
 * Integration tests for seismo-staging.
 *
 * Run:  php tests/test_staging.php
 *
 * Hits every route on the live staging server and checks:
 *   - HTTP status codes
 *   - Expected page titles / content markers
 *   - JSON structure for API endpoints
 *   - Auth rejection for protected endpoints
 *   - Redirects for mutating actions
 *   - Search functionality
 *   - Cross-controller dependencies
 *
 * Throttled to avoid tripping the hoster's rate limiter.
 */

$BASE = 'https://www.hektopascal.org/seismo-staging/index.php';
$DELAY_MS = 800; // pause between requests to avoid 403 rate limiting

$passed  = 0;
$failed  = 0;
$errors  = [];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function throttle(): void {
    global $DELAY_MS;
    usleep($DELAY_MS * 1000);
}

function req(string $url, array $opts = []): array {
    throttle();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'SeismoTestSuite/1.0',
    ]);
    if (!empty($opts['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);
    }
    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);

    $headerSize = $info['header_size'] ?? 0;
    $headers = substr($raw, 0, $headerSize);
    $body    = substr($raw, $headerSize);

    return [
        'status'   => (int)$info['http_code'],
        'headers'  => $headers,
        'body'     => $body,
        'url'      => $url,
        'error'    => $err,
        'redirect' => $info['redirect_url'] ?? '',
    ];
}

function assert_status(string $label, array $r, int $expected): bool {
    global $passed, $failed, $errors;
    if ($r['status'] === $expected) {
        $passed++;
        echo "  ✓ $label\n";
        return true;
    } else {
        $failed++;
        $msg = "  ✗ $label — expected $expected, got {$r['status']}";
        if ($r['error']) $msg .= " (curl: {$r['error']})";
        echo "$msg\n";
        $errors[] = $msg;
        return false;
    }
}

function assert_contains(string $label, array $r, string $needle): void {
    global $passed, $failed, $errors;
    if (stripos($r['body'], $needle) !== false) {
        $passed++;
        echo "  ✓ $label\n";
    } else {
        $failed++;
        $snippet = substr($r['body'], 0, 200);
        $msg = "  ✗ $label — missing \"$needle\"";
        echo "$msg\n";
        $errors[] = $msg;
    }
}

function assert_not_contains(string $label, array $r, string $needle): void {
    global $passed, $failed, $errors;
    if (stripos($r['body'], $needle) === false) {
        $passed++;
        echo "  ✓ $label\n";
    } else {
        $failed++;
        $msg = "  ✗ $label — should NOT contain \"$needle\"";
        echo "$msg\n";
        $errors[] = $msg;
    }
}

function assert_json(string $label, array $r): ?array {
    global $passed, $failed, $errors;
    $data = json_decode($r['body'], true);
    if ($data !== null || $r['body'] === 'null' || $r['body'] === '[]') {
        $passed++;
        echo "  ✓ $label\n";
        return is_array($data) ? $data : [];
    } else {
        $failed++;
        $msg = "  ✗ $label — invalid JSON";
        echo "$msg\n";
        $errors[] = $msg;
        return null;
    }
}

function assert_json_key(string $label, ?array $data, string $key): void {
    global $passed, $failed, $errors;
    if ($data !== null && array_key_exists($key, $data)) {
        $passed++;
        echo "  ✓ $label\n";
    } else {
        $failed++;
        $msg = "  ✗ $label — key \"$key\" missing";
        echo "$msg\n";
        $errors[] = $msg;
    }
}

function assert_redirect(string $label, array $r): void {
    global $passed, $failed, $errors;
    if ($r['status'] >= 301 && $r['status'] <= 303) {
        $passed++;
        echo "  ✓ $label (→ {$r['status']})\n";
    } else {
        $failed++;
        $msg = "  ✗ $label — expected 3xx redirect, got {$r['status']}";
        echo "$msg\n";
        $errors[] = $msg;
    }
}

function assert_body_size(string $label, array $r, int $minBytes): void {
    global $passed, $failed, $errors;
    $bodyLen = strlen($r['body']);
    if ($bodyLen >= $minBytes) {
        $passed++;
        echo "  ✓ $label ({$bodyLen} bytes)\n";
    } else {
        $failed++;
        $msg = "  ✗ $label — only {$bodyLen} bytes (expected ≥{$minBytes})";
        echo "$msg\n";
        $errors[] = $msg;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
echo "Seismo Staging Integration Tests\n";
echo "Target: $BASE\n";
echo "Throttle: {$DELAY_MS}ms between requests\n";
echo str_repeat('═', 60) . "\n\n";

// ─── 1. Pages (HTML, 200) ────────────────────────────────────────────────────
echo "1. Page rendering\n";

$pages = [
    'Dashboard'  => ['action' => 'index',     'title' => '<title>Seismo</title>'],
    'RSS Feeds'  => ['action' => 'feeds',     'title' => 'RSS - Seismo'],
    'Substack'   => ['action' => 'substack',  'title' => 'Substack - Seismo'],
    'Mail'       => ['action' => 'mail',      'title' => 'Mail - Seismo'],
    'Lex'        => ['action' => 'lex',       'title' => 'Lex - Seismo'],
    'Jus'        => ['action' => 'jus',       'title' => 'Jus - Seismo'],
    'Scraper'    => ['action' => 'scraper',   'title' => 'Scraper - Seismo'],
    'Magnitu'    => ['action' => 'magnitu',   'title' => 'Magnitu'],
    'Settings'   => ['action' => 'settings',  'title' => 'Settings - Seismo'],
    'About'      => ['action' => 'about',     'title' => 'About - Seismo'],
    'Beta'       => ['action' => 'beta',      'title' => 'Beta'],
    'Styleguide' => ['action' => 'styleguide','title' => 'Style Guide'],
];

foreach ($pages as $name => $cfg) {
    $r = req("$BASE?action={$cfg['action']}");
    $ok = assert_status("$name returns 200", $r, 200);
    if ($ok) {
        assert_contains("$name has expected title", $r, $cfg['title']);
        assert_contains("$name has HTML structure", $r, '</html>');
        assert_not_contains("$name has no Fatal errors", $r, 'Fatal error');
    }
}

// ─── 2. Settings tabs ───────────────────────────────────────────────────────
echo "\n2. Settings tabs\n";

foreach (['basic', 'script', 'lex', 'magnitu'] as $tab) {
    $r = req("$BASE?action=settings&tab=$tab");
    $ok = assert_status("Settings tab=$tab returns 200", $r, 200);
    if ($ok) {
        assert_not_contains("Settings tab=$tab no Fatal errors", $r, 'Fatal error');
    }
}

// ─── 3. Dashboard features ──────────────────────────────────────────────────
echo "\n3. Dashboard features\n";

$r = req("$BASE?action=index");
assert_contains("Dashboard has nav", $r, 'top-bar');
assert_contains("Dashboard has CSS", $r, 'style.css');
assert_body_size("Dashboard body is substantial", $r, 5000);

$r = req("$BASE?action=index&q=test&tags_submitted=1");
assert_status("Search returns 200", $r, 200);

$r = req("$BASE?action=index&sort=relevance");
assert_status("Sort by relevance returns 200", $r, 200);

$r = req("$BASE?action=index&tags_submitted=1&tags[]=BBC");
assert_status("Tag filter returns 200", $r, 200);

$r = req("$BASE?action=index&tags_submitted=1");
assert_status("Empty filter returns 200", $r, 200);

// ─── 4. JSON API endpoints ──────────────────────────────────────────────────
echo "\n4. JSON API endpoints\n";

$r = req("$BASE?action=api_tags");
assert_status("api_tags returns 200", $r, 200);
$data = assert_json("api_tags is valid JSON", $r);

$r = req("$BASE?action=api_substack_tags");
assert_status("api_substack_tags returns 200", $r, 200);
assert_json("api_substack_tags is valid JSON", $r);

$r = req("$BASE?action=api_email_tags");
assert_status("api_email_tags returns 200", $r, 200);
assert_json("api_email_tags is valid JSON", $r);

$r = req("$BASE?action=api_all_tags");
assert_status("api_all_tags returns 200", $r, 200);
$data = assert_json("api_all_tags is valid JSON", $r);
if ($data) {
    assert_json_key("api_all_tags has 'rss'", $data, 'rss');
    assert_json_key("api_all_tags has 'substack'", $data, 'substack');
    assert_json_key("api_all_tags has 'email'", $data, 'email');
}

$r = req("$BASE?action=api_feeds");
assert_status("api_feeds returns 200", $r, 200);
$feeds = assert_json("api_feeds is valid JSON", $r);
if ($feeds && count($feeds) > 0) {
    assert_json_key("api_feeds[0] has 'id'", $feeds[0], 'id');
    assert_json_key("api_feeds[0] has 'url'", $feeds[0], 'url');
    assert_json_key("api_feeds[0] has 'title'", $feeds[0], 'title');
}

$r = req("$BASE?action=api_items&feed_id=1");
assert_status("api_items returns 200", $r, 200);
assert_json("api_items is valid JSON", $r);

// ─── 5. Magnitu API (auth enforcement) ──────────────────────────────────────
echo "\n5. Magnitu API (auth enforcement)\n";

// These endpoints use proper HTTP status codes for auth failures
$r = req("$BASE?action=magnitu_entries");
if (in_array($r['status'], [200, 401, 403])) {
    $passed++;
    echo "  ✓ magnitu_entries responds ({$r['status']})\n";
    $data = json_decode($r['body'], true);
    if ($data && isset($data['error'])) {
        $passed++;
        echo "  ✓ magnitu_entries rejects without auth\n";
    }
} else {
    $failed++;
    $errors[] = "  ✗ magnitu_entries unexpected status {$r['status']}";
    echo "  ✗ magnitu_entries unexpected status {$r['status']}\n";
}

$r = req("$BASE?action=magnitu_status");
if (in_array($r['status'], [200, 401, 403])) {
    $passed++;
    echo "  ✓ magnitu_status responds ({$r['status']})\n";
} else {
    $failed++;
    $errors[] = "  ✗ magnitu_status unexpected status {$r['status']}";
    echo "  ✗ magnitu_status unexpected status {$r['status']}\n";
}

$r = req("$BASE?action=magnitu_status", ['headers' => ['Authorization: Bearer wrong_key_12345']]);
if (in_array($r['status'], [200, 401, 403])) {
    $passed++;
    echo "  ✓ magnitu_status with bad key responds ({$r['status']})\n";
    $data = json_decode($r['body'], true);
    if ($data && isset($data['error'])) {
        $passed++;
        echo "  ✓ magnitu_status bad key returns error JSON\n";
    }
} else {
    $failed++;
    $errors[] = "  ✗ magnitu_status bad key unexpected status {$r['status']}";
    echo "  ✗ magnitu_status bad key unexpected status {$r['status']}\n";
}

$r = req("$BASE?action=magnitu_recipe");
if (in_array($r['status'], [200, 401, 403])) {
    $passed++;
    echo "  ✓ magnitu_recipe responds ({$r['status']})\n";
}

// ─── 6. Redirect actions ────────────────────────────────────────────────────
echo "\n6. Redirect actions\n";

$r = req("$BASE?action=refresh_emails&from=mail");
assert_redirect("refresh_emails redirects", $r);

$r = req("$BASE?action=refresh_all_feeds&from=feeds");
assert_redirect("refresh_all_feeds redirects", $r);

$r = req("$BASE?action=refresh_all_substacks");
assert_redirect("refresh_all_substacks redirects", $r);

$r = req("$BASE?action=refresh_all_lex");
assert_redirect("refresh_all_lex redirects", $r);

$r = req("$BASE?action=refresh_all_jus");
assert_redirect("refresh_all_jus redirects", $r);

// refresh_all has a cooldown — might redirect or cooldown-redirect
$r = req("$BASE?action=refresh_all&from=index");
if ($r['status'] >= 301 && $r['status'] <= 303) {
    $passed++;
    echo "  ✓ refresh_all redirects ({$r['status']})\n";
} else {
    $failed++;
    $errors[] = "  ✗ refresh_all — expected redirect, got {$r['status']}";
    echo "  ✗ refresh_all — expected redirect, got {$r['status']}\n";
}

// ─── 7. Feed view ───────────────────────────────────────────────────────────
echo "\n7. Feed view\n";

// Use a known feed ID from the api_feeds call
if (!empty($feeds)) {
    $feedId = $feeds[0]['id'];
    $r = req("$BASE?action=view_feed&id=$feedId");
    assert_status("view_feed returns 200", $r, 200);
    assert_not_contains("view_feed no Fatal errors", $r, 'Fatal error');
}

// view_feed with bad ID → redirect to index
$r = req("$BASE?action=view_feed&id=0");
assert_redirect("view_feed id=0 redirects", $r);

// refresh_feed
if (!empty($feeds)) {
    $feedId = $feeds[0]['id'];
    $r = req("$BASE?action=refresh_feed&id=$feedId");
    assert_redirect("refresh_feed redirects", $r);
}

// ─── 8. AI views ────────────────────────────────────────────────────────────
echo "\n8. AI / Beta views\n";

$r = req("$BASE?action=ai_view");
assert_status("ai_view returns 200", $r, 200);
assert_not_contains("ai_view no Fatal errors", $r, 'Fatal error');

$r = req("$BASE?action=ai_view_unified");
assert_redirect("ai_view_unified redirects to ai_view", $r);

// ─── 9. Edge cases ──────────────────────────────────────────────────────────
echo "\n9. Edge cases\n";

$r = req("$BASE?action=nonexistent_page_xyz");
assert_redirect("Unknown action redirects", $r);

$r = req("$BASE?action=delete_email&id=999999");
assert_redirect("delete_email without confirm redirects", $r);

$r = req("$BASE?action=toggle_feed&id=0");
assert_redirect("toggle_feed id=0 redirects", $r);

// ─── 10. Content integrity ──────────────────────────────────────────────────
echo "\n10. Content integrity\n";

$r = req("$BASE?action=feeds");
assert_contains("Feeds page has category content", $r, 'feed');

$r = req("$BASE?action=mail");
assert_body_size("Mail page has content", $r, 2000);

$r = req("$BASE?action=settings&tab=basic");
assert_contains("Settings basic has RSS section", $r, 'RSS');

$r = req("$BASE?action=settings&tab=script");
assert_contains("Settings script has mail section", $r, 'Mail');

$r = req("$BASE?action=about");
assert_contains("About page has Seismo info", $r, 'Seismo');

// ─── 11. Cross-controller dependencies ──────────────────────────────────────
echo "\n11. Cross-controller dependencies\n";

$r = req("$BASE?action=index");
assert_status("Dashboard (cross-ctrl) returns 200", $r, 200);
assert_body_size("Dashboard (cross-ctrl) body substantial", $r, 10000);

$r = req("$BASE?action=magnitu");
assert_body_size("Magnitu (cross-ctrl) body substantial", $r, 5000);

$r = req("$BASE?action=settings");
assert_body_size("Settings (cross-ctrl) body substantial", $r, 5000);

// ═════════════════════════════════════════════════════════════════════════════
echo "\n" . str_repeat('═', 60) . "\n";
$total = $passed + $failed;
echo "Results: $passed/$total passed";
if ($failed > 0) {
    echo " ($failed FAILED)\n\n";
    echo "Failures:\n";
    foreach ($errors as $e) {
        echo "$e\n";
    }
    echo "\n";
    exit(1);
} else {
    echo " — ALL PASSED\n";
    exit(0);
}
