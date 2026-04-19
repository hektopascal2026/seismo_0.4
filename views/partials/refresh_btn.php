<?php
/**
 * views/partials/refresh_btn.php
 *
 * Shared top-bar / floating refresh control. On the mothership this is a
 * standard `<a href="?action=refresh_all&from=…">` link. On a satellite it
 * swaps to a JS-driven button whose click fires a `fetch()` to the mothership's
 * `refresh_all_remote` endpoint, then reloads the page so freshly-scraped
 * mothership entries appear via the cross-DB read.
 *
 * Vars (all optional):
 *   $refreshFrom  — value for `&from=…` so flash redirects land on this page (default: 'index')
 *   $refreshExtra — additional query string to append to the link href (default: '')
 *   $refreshStyle — 'icon' (default, top-bar SVG) or 'floating' (text pill)
 */

$refreshFrom  = $refreshFrom  ?? 'index';
$refreshExtra = $refreshExtra ?? '';
$refreshStyle = $refreshStyle ?? 'icon';

$__refreshClass = $refreshStyle === 'floating' ? 'floating-refresh-btn' : 'top-bar-btn';
$__refreshHref  = '?action=refresh_all&from=' . urlencode($refreshFrom) . $refreshExtra;
$__refreshIcon  = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>';
$__refreshBody  = $refreshStyle === 'floating' ? 'Refresh' : $__refreshIcon;

if (isSatellite()):
    $__motherUrl  = defined('SEISMO_MOTHERSHIP_URL') ? (string)SEISMO_MOTHERSHIP_URL : '';
    $__refreshKey = defined('SEISMO_REMOTE_REFRESH_KEY') ? (string)SEISMO_REMOTE_REFRESH_KEY : '';
?>
<button type="button"
        class="satellite-refresh-btn <?= htmlspecialchars($__refreshClass) ?>"
        title="Refresh mothership data"
        data-mothership="<?= htmlspecialchars($__motherUrl, ENT_QUOTES) ?>"
        data-key="<?= htmlspecialchars($__refreshKey, ENT_QUOTES) ?>"><?= $__refreshBody ?></button>
<?php
    // Emit the click handler once per page.
    if (empty($GLOBALS['__seismo_sat_refresh_js_emitted'])):
        $GLOBALS['__seismo_sat_refresh_js_emitted'] = true;
?>
<style>
button.satellite-refresh-btn { background: transparent; cursor: pointer; font: inherit; }
button.satellite-refresh-btn[disabled] { opacity: 0.5; cursor: wait; }
button.satellite-refresh-btn.satellite-refresh-spinning svg { animation: satRefreshSpin 1s linear infinite; }
@keyframes satRefreshSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
<script>
(function () {
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.satellite-refresh-btn');
        if (!btn) return;
        e.preventDefault();
        if (btn.disabled) return;

        const mother = (btn.dataset.mothership || '').replace(/\/+$/, '');
        const key = btn.dataset.key || '';
        if (!mother || !key) {
            alert('Satellite is missing SEISMO_MOTHERSHIP_URL or SEISMO_REMOTE_REFRESH_KEY in config.local.php.');
            return;
        }
        const url = mother + '/?action=refresh_all_remote&key=' + encodeURIComponent(key);

        // Disable all refresh buttons on the page while the call is in flight.
        const allButtons = document.querySelectorAll('.satellite-refresh-btn');
        allButtons.forEach(b => { b.disabled = true; b.classList.add('satellite-refresh-spinning'); });

        try {
            const res = await fetch(url, { method: 'GET', credentials: 'omit' });
            let payload = null;
            try { payload = await res.json(); } catch (_) {}
            if (res.ok && payload && payload.ok) {
                // Success — reload so satellite picks up new entries via cross-DB read.
                window.location.reload();
                return;
            }
            let msg = 'Refresh failed (HTTP ' + res.status + ')';
            if (payload && payload.error) msg += ': ' + payload.error;
            if (payload && payload.retry_after) msg += ' · retry in ' + payload.retry_after + 's';
            alert(msg);
        } catch (err) {
            alert('Network error calling mothership: ' + (err && err.message ? err.message : err));
        } finally {
            allButtons.forEach(b => { b.disabled = false; b.classList.remove('satellite-refresh-spinning'); });
        }
    });
})();
</script>
<?php endif; ?>
<?php else: ?>
<a href="<?= htmlspecialchars($__refreshHref, ENT_QUOTES) ?>" class="<?= htmlspecialchars($__refreshClass) ?>" title="Refresh all sources"><?= $__refreshBody ?></a>
<?php endif; ?>
