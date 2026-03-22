<?php
/** @var array $diag */
$summary = $diag['summary'];
$rows = $diag['rows'];
$report = $diag['_report'];
$elapsed = $diag['_elapsed'];

$plainParams = ['action' => 'feed_diagnostics', 'format' => 'text'];
if (FEED_DIAGNOSTIC_KEY !== '' && isset($_GET['key'])) {
    $plainParams['key'] = (string)$_GET['key'];
}
$plainUrl = getBasePath() . '/index.php?' . http_build_query($plainParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Feed diagnostics - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
    <style>
        .diag-wrap { max-width: 1100px; margin: 0 auto; }
        .diag-summary {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            margin: 16px 0 20px;
        }
        .diag-stat {
            border: 2px solid #000;
            padding: 10px 12px;
            background: #fff;
        }
        .diag-stat strong { display: block; font-size: 20px; }
        .diag-stat span { font-size: 11px; color: #333; }
        .diag-stat.ok strong { color: #0a0; }
        .diag-stat.bad strong { color: #c00; }
        .diag-table-wrap { overflow-x: auto; border: 2px solid #000; margin-bottom: 16px; }
        table.diag-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.diag-table th, table.diag-table td {
            border-bottom: 1px solid #000;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        table.diag-table th { background: #f5f5f5; }
        tr.diag-ok td:first-child { border-left: 4px solid #0a0; }
        tr.diag-fail td:first-child { border-left: 4px solid #c00; }
        tr.diag-skip td:first-child { border-left: 4px solid #999; }
        .diag-hint { color: #333; font-size: 11px; max-width: 420px; }
        .diag-log {
            width: 100%;
            min-height: 280px;
            font-family: ui-monospace, monospace;
            font-size: 11px;
            padding: 12px;
            border: 2px solid #000;
            background: #fafafa;
            box-sizing: border-box;
        }
        .diag-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 12px 0; }
    </style>
</head>
<body>
    <div class="container diag-wrap">
        <div class="top-bar">
            <div class="top-bar-left">
                <span class="top-bar-title">
                    <a href="?action=index">
                        <svg class="logo-icon logo-icon-large" viewBox="0 0 24 16" xmlns="http://www.w3.org/2000/svg">
                            <rect width="24" height="16" fill="#FFFFC5"/>
                            <path d="M0,8 L4,12 L6,4 L10,10 L14,2 L18,8 L20,6 L24,8" stroke="#000000" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    Feed diagnostics
                </span>
                <span class="top-bar-subtitle">Read-only check (no DB updates)</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=feeds" class="top-bar-btn" title="RSS">RSS</a>
                <a href="?action=settings&amp;tab=basic" class="top-bar-btn" title="Settings">Settings</a>
            </div>
        </div>

        <p style="font-size: 13px; margin: 12px 0;">
            Each feed is fetched with the same cURL settings as a normal refresh, then parsed with SimplePie.
            Tripped feeds (3+ failures) are still tested here. Scraper rows are listed but not fetched as RSS.
            <?php if (FEED_DIAGNOSTIC_KEY !== ''): ?>
                This page is key-protected; do not share the URL with the secret query parameter.
            <?php else: ?>
                Anyone who can open your Seismo instance can run this; add <code>FEED_DIAGNOSTIC_KEY</code> in <code>config.local.php</code> to require <code>?key=</code>.
            <?php endif; ?>
        </p>

        <div class="diag-summary">
            <div class="diag-stat"><strong><?= (int)$summary['total'] ?></strong><span>in database</span></div>
            <div class="diag-stat ok"><strong><?= (int)$summary['ok'] ?></strong><span>OK</span></div>
            <div class="diag-stat bad"><strong><?= (int)$summary['fetch_fail'] ?></strong><span>fetch failed</span></div>
            <div class="diag-stat bad"><strong><?= (int)$summary['parse_fail'] ?></strong><span>parse failed</span></div>
            <div class="diag-stat"><strong><?= (int)$summary['skipped_scraper'] ?></strong><span>scraper (skipped)</span></div>
            <div class="diag-stat"><strong><?= (int)$summary['disabled'] ?></strong><span>disabled in DB</span></div>
            <div class="diag-stat"><strong><?= htmlspecialchars(number_format($elapsed, 2)) ?>s</strong><span>wall time</span></div>
        </div>

        <div class="diag-actions">
            <button type="button" class="btn btn-primary" id="copyDiagLog">Copy full log</button>
            <a class="btn btn-secondary" href="<?= htmlspecialchars($plainUrl) ?>">Open plain text</a>
            <a class="btn btn-secondary" href="?action=feed_diagnostics<?= FEED_DIAGNOSTIC_KEY !== '' && isset($_GET['key']) ? '&amp;key=' . urlencode((string)$_GET['key']) : '' ?>">Run again</a>
        </div>

        <div class="diag-table-wrap">
            <table class="diag-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Title / URL</th>
                        <th>HTTP</th>
                        <th>Items</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $trClass = 'diag-skip';
                    if ($r['status'] === 'ok') {
                        $trClass = 'diag-ok';
                    } elseif ($r['status'] === 'fetch_fail' || $r['status'] === 'parse_fail') {
                        $trClass = 'diag-fail';
                    }
                    ?>
                    <tr class="<?= $trClass ?>">
                        <td><?= (int)$r['id'] ?></td>
                        <td><code><?= htmlspecialchars($r['status']) ?></code></td>
                        <td>
                            <strong><?= htmlspecialchars($r['title'] !== '' ? $r['title'] : '(no title)') ?></strong><br>
                            <span style="word-break: break-all;"><?= htmlspecialchars($r['url']) ?></span>
                            <?php if (!empty($r['disabled'])): ?>
                                <br><span style="font-size:11px;">disabled in DB</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'skipped_scraper'): ?>—<?php else: ?>
                                <?= (int)$r['http_code'] ?><br>
                                <span style="font-size:11px;"><?= (int)$r['bytes'] ?> B · <?= htmlspecialchars(number_format($r['total_time'], 2)) ?>s</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['item_count'] !== null ? (int)$r['item_count'] : '—' ?></td>
                        <td class="diag-hint">
                            <?php if (!empty($r['curl_error'])): ?>
                                <strong>cURL:</strong> <?= htmlspecialchars($r['curl_error']) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($r['parse_error'])): ?>
                                <strong>Parse:</strong> <?= htmlspecialchars(mb_substr($r['parse_error'], 0, 200)) ?><?= mb_strlen($r['parse_error']) > 200 ? '…' : '' ?><br>
                            <?php endif; ?>
                            <?php if (!empty($r['hint'])): ?>
                                <?= htmlspecialchars($r['hint']) ?>
                            <?php endif; ?>
                            <?php if (!empty($r['last_error']) && ($r['status'] !== 'ok')): ?>
                                <br><strong>Last DB error:</strong> <?= htmlspecialchars(mb_substr($r['last_error'], 0, 120)) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="section-title" style="margin-top: 24px;">Full log (paste elsewhere)</h2>
        <textarea class="diag-log" readonly id="diagLogArea"><?= htmlspecialchars($report) ?></textarea>
    </div>
    <script>
    document.getElementById('copyDiagLog').addEventListener('click', function () {
        var ta = document.getElementById('diagLogArea');
        var text = ta.value;
        var btn = this;
        function doneOk() {
            btn.textContent = 'Copied';
            setTimeout(function () { btn.textContent = 'Copy full log'; }, 2000);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(doneOk).catch(function () {
                ta.select();
                document.execCommand('copy');
                doneOk();
            });
            return;
        }
        ta.select();
        ta.setSelectionRange(0, text.length);
        try {
            document.execCommand('copy');
            doneOk();
        } catch (e) {
            alert('Select the log in the box and copy manually (Cmd/Ctrl+C).');
        }
    });
    </script>
</body>
</html>
