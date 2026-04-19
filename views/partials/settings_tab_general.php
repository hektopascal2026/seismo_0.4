<?php
/**
 * views/partials/settings_tab_general.php
 *
 * General settings landing — short overview, satellite-mode indicator, and
 * pointers to the per-module management UIs. This tab is intentionally thin:
 * real knobs live on the module tabs themselves.
 *
 * Expects: $satellitesMothershipDb (nullable), isSatellite() helper.
 */
$isSat = function_exists('isSatellite') && isSatellite();
?>
<p style="font-size: 12px; margin-bottom: 16px;">
    Seismo-wide overview. Per-module configuration lives on the tabs above and on each module's own page.
</p>

<?php if ($isSat): ?>
<section class="settings-section" style="background: #f5f0ff; border: 2px solid #000; padding: 12px 14px; margin-bottom: 16px;">
    <h2 style="background-color: #C5B4D1; padding: 8px 14px; display: inline-block; margin-top: 0;">Satellite mode</h2>
    <p style="font-size: 12px; margin: 8px 0 0 0; line-height: 1.5;">
        This instance is a <strong>satellite</strong>. Entry-source tables are read from the mothership database
        <?php if (!empty($satellitesMothershipDb ?? null)): ?>
            (<code><?= htmlspecialchars((string)$satellitesMothershipDb) ?></code>)
        <?php endif; ?>
        while scoring tables (<code>entry_scores</code>, <code>magnitu_config</code>, <code>magnitu_labels</code>) are local.
    </p>
</section>
<?php endif; ?>

<section class="settings-section">
    <h2 style="background-color: #ffffff; border: 2px solid #000; padding: 8px 14px; display: inline-block; margin-top: 0;">Where to find things</h2>
    <ul style="font-size: 13px; line-height: 1.8; margin: 10px 0 0 0; padding-left: 18px;">
        <li><strong>RSS feeds</strong> — add / tag / disable on <a href="<?= getBasePath() ?>/index.php?action=feeds&amp;view=feeds">RSS &rsaquo; Feeds</a>.</li>
        <li><strong>Substack newsletters</strong> — manage on <a href="?action=settings&amp;tab=rss">Settings &rsaquo; RSS</a> (they are RSS under the hood).</li>
        <li><strong>Mail subscriptions</strong> — inbox senders &amp; unsubscribes on <a href="<?= getBasePath() ?>/index.php?action=mail&amp;view=subscriptions">Mail &rsaquo; Subscriptions</a>; IMAP credentials on <a href="?action=settings&amp;tab=mail">Settings &rsaquo; Mail</a>.</li>
        <li><strong>Scraper URLs</strong> — on <a href="?action=settings&amp;tab=scraper">Settings &rsaquo; Scraper</a>.</li>
        <li><strong>Lex / Jus sources</strong> — on <a href="?action=settings&amp;tab=lex">Settings &rsaquo; Lex / Jus</a>.</li>
        <li><strong>Leg (parliamentary activity)</strong> — on <a href="?action=settings&amp;tab=leg">Settings &rsaquo; Leg</a>.</li>
        <li><strong>Magnitu</strong> — ML scoring config on <a href="?action=settings&amp;tab=magnitu">Settings &rsaquo; Magnitu</a>.</li>
        <li><strong>LLM tools</strong> — AI unified-feed generator on <a href="?action=settings&amp;tab=llm">Settings &rsaquo; LLM</a>.</li>
        <li><strong>Diagnostics</strong> — source health checks on <a href="?action=settings&amp;tab=diagnostics">Settings &rsaquo; Diagnostics</a>.</li>
    </ul>
</section>
