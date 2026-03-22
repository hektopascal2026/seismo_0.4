<?php
/** @var array $feedDiagnosticsData */
$diag = $feedDiagnosticsData;
$summary = $diag['summary'];
$rows = $diag['rows'];
$report = $diag['_report'];
$elapsed = $diag['_elapsed'];

$plainParams = ['action' => 'settings', 'tab' => 'feed_diagnostics', 'format' => 'text'];
if (FEED_DIAGNOSTIC_KEY !== '' && isset($_GET['key'])) {
    $plainParams['key'] = (string)$_GET['key'];
}
$plainUrl = getBasePath() . '/index.php?' . http_build_query($plainParams);

$runAgainParams = ['action' => 'settings', 'tab' => 'feed_diagnostics'];
if (FEED_DIAGNOSTIC_KEY !== '' && isset($_GET['key'])) {
    $runAgainParams['key'] = (string)$_GET['key'];
}
$runAgainUrl = getBasePath() . '/index.php?' . http_build_query($runAgainParams);
?>
<section class="settings-section" style="border-bottom: none;">
    <h2 style="background-color: #e8e0f5; padding: 8px 14px; display: inline-block;">Feed diagnostics</h2>
    <p style="font-size: 13px; margin: 12px 0;">
        Read-only check (no database updates). Each feed is fetched with the same cURL settings as a normal refresh, then parsed with SimplePie.
        Tripped feeds (3+ failures) are still tested here. Scraper rows are listed but not fetched as RSS.
        <?php if (FEED_DIAGNOSTIC_KEY !== ''): ?>
            This tab is key-protected; do not share the URL with the secret query parameter.
        <?php else: ?>
            Anyone who can open Settings can run this; add <code>FEED_DIAGNOSTIC_KEY</code> in <code>config.local.php</code> to require <code>?key=</code> on the URL.
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
        <a class="btn btn-secondary" href="<?= htmlspecialchars($runAgainUrl) ?>">Run again</a>
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
</section>
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
