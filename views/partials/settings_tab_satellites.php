<?php
/**
 * views/partials/settings_tab_satellites.php
 *
 * Settings → Satellites tab. Mothership-only. Lets the operator add / remove /
 * rotate satellite Seismo instances, and download each one's `satellite.json`
 * ready to feed into the external seismo-generator tool.
 *
 * Context vars (set by controllers/settings.php handleSettingsPage):
 *   $satellitesRegistry                       — array of rows {slug, display_name, magnitu_profile, brand_accent, api_key, created_at, rotated_at?}
 *   $satellitesMothershipUrl                  — detected absolute URL for this Seismo
 *   $satellitesMothershipDb                   — current DATABASE() value
 *   $satellitesRemoteRefreshKeyConfigured     — bool, is SEISMO_REMOTE_REFRESH_KEY set?
 *   $satellitesSuggestedRefreshKey            — suggested value when user clicks "Rotate"
 *   $satellitesHighlightSlug                  — slug to visually highlight after add/rotate
 */
?>
<section style="margin-top: 8px;">
    <h2 style="margin-bottom: 8px;">Satellites</h2>
    <p style="font-size: 13px; color: #333; max-width: 640px; margin-bottom: 16px;">
        Register lightweight satellite Seismo instances that read entries from this mothership and render them scored by a dedicated Magnitu profile.
        After adding a satellite here, download its JSON and feed it into the <code>seismo-generator</code> tool to build a deployable folder.
    </p>

    <?php if (!$satellitesRemoteRefreshKeyConfigured): ?>
    <div style="background: #fff3cd; border: 2px solid #000; padding: 10px 14px; margin-bottom: 16px; font-size: 13px;">
        <strong>SEISMO_REMOTE_REFRESH_KEY is not set on this mothership.</strong>
        Satellites can still pull entries, but their "Refresh" button (which calls back into this mothership) will fail.
        <?php if ($satellitesSuggestedRefreshKey): ?>
            <div style="margin-top: 8px;">
                Suggested value:
                <code style="background: #fff; padding: 2px 6px; border: 1px solid #000; font-size: 12px; user-select: all;"><?= htmlspecialchars($satellitesSuggestedRefreshKey) ?></code>
            </div>
            <div style="margin-top: 8px; font-size: 12px;">
                Add to mothership <code>config.local.php</code>:
                <code style="display: block; margin-top: 4px; background: #fff; padding: 6px 8px; border: 1px solid #000; font-size: 12px; white-space: pre;">define('SEISMO_REMOTE_REFRESH_KEY', '<?= htmlspecialchars($satellitesSuggestedRefreshKey) ?>');</code>
            </div>
        <?php else: ?>
            <form method="post" action="?action=satellite_rotate_refresh_key" style="display: inline-block; margin-top: 8px;">
                <button type="submit" class="btn btn-secondary" style="font-size: 12px;">Generate suggested key</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Registry -->
    <?php if (empty($satellitesRegistry)): ?>
        <div class="empty-state" style="padding: 20px; font-size: 13px;">
            No satellites registered yet. Use the form below to add your first one.
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px;">
            <thead>
                <tr style="background: #f5f5f5; border-bottom: 2px solid #000;">
                    <th style="text-align: left; padding: 8px;">Slug</th>
                    <th style="text-align: left; padding: 8px;">Display name</th>
                    <th style="text-align: left; padding: 8px;">Magnitu profile</th>
                    <th style="text-align: left; padding: 8px;">Accent</th>
                    <th style="text-align: left; padding: 8px;">Created</th>
                    <th style="text-align: right; padding: 8px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($satellitesRegistry as $sat): ?>
                    <?php
                    $slug = $sat['slug'] ?? '';
                    $isHighlight = ($slug !== '' && $slug === $satellitesHighlightSlug);
                    ?>
                    <tr style="border-bottom: 1px solid #ddd;<?= $isHighlight ? ' background: #fff3cd;' : '' ?>">
                        <td style="padding: 8px;"><code><?= htmlspecialchars($slug) ?></code></td>
                        <td style="padding: 8px;"><?= htmlspecialchars($sat['display_name'] ?? '') ?></td>
                        <td style="padding: 8px;"><code><?= htmlspecialchars($sat['magnitu_profile'] ?? '') ?></code></td>
                        <td style="padding: 8px;">
                            <?php if (!empty($sat['brand_accent'])): ?>
                                <span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; background: <?= htmlspecialchars($sat['brand_accent']) ?>; border: 1px solid #000;"></span>
                                <code style="font-size: 11px;"><?= htmlspecialchars($sat['brand_accent']) ?></code>
                            <?php else: ?>
                                <span style="color: #999; font-size: 11px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 8px; font-size: 11px; color: #666;">
                            <?= htmlspecialchars(substr((string)($sat['created_at'] ?? ''), 0, 10)) ?>
                            <?php if (!empty($sat['rotated_at'])): ?>
                                <br><span style="color: #a00;">rotated <?= htmlspecialchars(substr((string)$sat['rotated_at'], 0, 10)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 8px; text-align: right; white-space: nowrap;">
                            <a href="?action=satellite_download_json&slug=<?= urlencode($slug) ?>" class="btn btn-primary" style="font-size: 11px; padding: 4px 8px;">Download JSON</a>
                            <form method="post" action="?action=satellite_rotate_key" style="display: inline;" onsubmit="return confirm('Rotate API key for <?= htmlspecialchars($slug, ENT_QUOTES) ?>? The old key stops working immediately and you must redeploy the satellite.');">
                                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
                                <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px;">Rotate key</button>
                            </form>
                            <form method="post" action="?action=satellite_remove" style="display: inline;" onsubmit="return confirm('Remove <?= htmlspecialchars($slug, ENT_QUOTES) ?> from registry? The satellite itself is untouched, but you lose the ability to generate its JSON here.');">
                                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
                                <button type="submit" class="btn btn-danger" style="font-size: 11px; padding: 4px 8px;">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Add satellite -->
    <details <?= empty($satellitesRegistry) ? 'open' : '' ?> style="border: 2px solid #000; padding: 12px 16px; margin-bottom: 20px;">
        <summary style="cursor: pointer; font-weight: 600; font-size: 14px;">Add a satellite</summary>

        <form method="post" action="?action=satellite_add" style="margin-top: 12px; display: grid; grid-template-columns: 180px 1fr; gap: 8px 16px; max-width: 640px; font-size: 13px;">
            <label for="sat_slug" style="align-self: center;">Slug</label>
            <input type="text" id="sat_slug" name="slug" required pattern="[a-z0-9-]+" maxlength="40" placeholder="digital" style="padding: 6px 8px; border: 1px solid #000;">

            <label for="sat_name" style="align-self: center;">Display name</label>
            <input type="text" id="sat_name" name="display_name" maxlength="80" placeholder="Seismo Digital" style="padding: 6px 8px; border: 1px solid #000;">

            <label for="sat_profile" style="align-self: center;">Magnitu profile</label>
            <input type="text" id="sat_profile" name="magnitu_profile" maxlength="40" pattern="[a-z0-9-]+" placeholder="(same as slug)" style="padding: 6px 8px; border: 1px solid #000;">

            <label for="sat_accent" style="align-self: center;">Brand accent (optional)</label>
            <input type="text" id="sat_accent" name="brand_accent" maxlength="9" placeholder="#4a90e2" pattern="#[0-9a-fA-F]{3,8}" style="padding: 6px 8px; border: 1px solid #000;">

            <div></div>
            <button type="submit" class="btn btn-primary" style="justify-self: start;">Add satellite</button>
        </form>
    </details>

    <!-- Context block -->
    <div style="font-size: 12px; color: #555; border-top: 1px solid #ccc; padding-top: 12px;">
        <strong>Detected mothership values</strong> (baked into each downloaded JSON):
        <ul style="margin: 6px 0 0 20px; padding: 0;">
            <li>URL: <code><?= htmlspecialchars($satellitesMothershipUrl) ?></code></li>
            <li>DB: <code><?= htmlspecialchars($satellitesMothershipDb) ?></code></li>
            <li>Remote refresh key:
                <?php if ($satellitesRemoteRefreshKeyConfigured): ?>
                    <code style="color: #060;">configured</code>
                <?php else: ?>
                    <code style="color: #a00;">NOT SET — satellites will not be able to trigger refresh</code>
                <?php endif; ?>
            </li>
        </ul>
    </div>
</section>
