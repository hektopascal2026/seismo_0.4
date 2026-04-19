<?php
/**
 * views/partials/mail_subscriptions_panel.php
 *
 * Renders the Mail → Subscriptions management UI. Expects the caller to
 * provide these variables (see esLoadSubscriptionsPageData()):
 *   $subscriptions, $categories, $selectedCategory, $showRemoved,
 *   $totalActive, $disabledCount, $removedCount
 *
 * All internal links point back to ?action=mail&view=subscriptions so this
 * partial can be included directly from views/mail.php without disturbing
 * the items view.
 */
$subsBase = '?action=mail&view=subscriptions';
?>
<style>
    .msp-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .msp-state {
        display: inline-flex;
        border: 1px solid #000000;
        background: #ffffff;
    }
    .msp-state a {
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 600;
        color: #000000;
        text-decoration: none;
        border-right: 1px solid #000000;
    }
    .msp-state a:last-child { border-right: 0; }
    .msp-state a.active { background: #FFDBBB; }
    .msp-state a:hover { background: #fff3e0; }
    .msp-state a.active:hover { background: #FFDBBB; }
    .msp-toolbar-right { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .msp-toolbar-right form { margin: 0; }

    .msp-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .msp-item {
        border: 1px solid #000000;
        padding: 14px 16px;
        background: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }
    .msp-item.paused  { background: #fafafa; opacity: 0.85; }
    .msp-item.removed { background: #f7f7f7; }

    .msp-info { flex: 1; min-width: 240px; }
    .msp-title {
        font-size: 15px;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: #000000;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .msp-matcher {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 12px;
        color: #555;
        word-break: break-all;
    }
    .msp-meta {
        display: flex;
        gap: 14px;
        margin-top: 6px;
        font-size: 12px;
        color: #555;
        flex-wrap: wrap;
    }
    .msp-meta strong { color: #000; }
    .msp-chip {
        display: inline-block;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid #000;
        background: #ffffff;
    }
    .msp-chip.paused   { background: #fff3e0; }
    .msp-chip.cat      { background: #FFDBBB; }
    .msp-chip.oneclick { background: #e6f4ea; }
    .msp-chip.auto     { background: #f0f0f0; color: #555; }

    .msp-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .msp-actions form { margin: 0; display: inline-block; }
    .msp-actions .btn,
    .msp-actions a.btn { padding: 5px 10px; font-size: 12px; }

    .msp-edit {
        display: none;
        width: 100%;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #ccc;
    }
    .msp-edit.open { display: block; }
    .msp-edit .form-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .msp-edit label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 3px;
        color: #000;
    }
    .msp-edit input[type="text"] {
        padding: 5px 8px;
        border: 1px solid #000;
        font-size: 13px;
        background: #fff;
        min-width: 160px;
    }

    .msp-add {
        display: none;
        margin-bottom: 18px;
        padding: 14px 16px;
        border: 1px solid #000000;
        background: #fffbea;
    }
    .msp-add.open { display: block; }
    .msp-add .form-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .msp-add label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 3px;
    }
    .msp-add input[type="text"],
    .msp-add select {
        padding: 6px 10px;
        border: 1px solid #000;
        font-size: 13px;
        background: #fff;
        min-width: 180px;
    }

    .msp-help {
        font-size: 12px;
        color: #555;
        margin: 0 0 18px 0;
        line-height: 1.5;
    }
    .msp-help code {
        background: #f5f5f5;
        padding: 1px 4px;
        font-size: 11px;
    }
</style>

<p class="msp-help">
    Email senders Seismo has seen. Matches are by <code>@domain</code> by default, or an exact address for overrides.
    Newly seen senders are added automatically. <strong>Pause</strong> to hide future mail without deleting, <strong>Remove</strong> to hide and stop counting, <strong>Unsubscribe</strong> to use the sender's <code>List-Unsubscribe</code> header when present.
</p>

<!-- Add subscription (hidden until toggled) -->
<div class="msp-add" id="mspAddForm">
    <form method="post" action="<?= getBasePath() ?>/index.php?action=add_email_subscription">
        <div class="form-row">
            <div>
                <label for="msp_match_type">Match</label>
                <select name="match_type" id="msp_match_type">
                    <option value="domain">Domain</option>
                    <option value="email">Exact address</option>
                </select>
            </div>
            <div>
                <label for="msp_match_value">Value</label>
                <input type="text" name="match_value" id="msp_match_value" placeholder="example.com or name@example.com">
            </div>
            <div>
                <label for="msp_display_name">Display name</label>
                <input type="text" name="display_name" id="msp_display_name" placeholder="Optional">
            </div>
            <div>
                <label for="msp_category">Category</label>
                <input type="text" name="category" id="msp_category" placeholder="unsortiert" value="unsortiert">
            </div>
            <div>
                <button type="submit" class="btn">Add subscription</button>
            </div>
        </div>
    </form>
</div>

<!-- Toolbar: state switch + category filter + actions -->
<div class="msp-toolbar">
    <div class="msp-state">
        <a href="<?= $subsBase ?>" class="<?= !$showRemoved ? 'active' : '' ?>">Active <span style="opacity:.7;">(<?= (int)$totalActive ?>)</span></a>
        <a href="<?= $subsBase ?>&amp;show_removed=1" class="<?= $showRemoved ? 'active' : '' ?>">Removed <span style="opacity:.7;">(<?= (int)$removedCount ?>)</span></a>
    </div>
    <div class="msp-toolbar-right">
        <?php if (!empty($categories) && !$showRemoved): ?>
            <div class="category-filter" style="margin: 0;">
                <a href="<?= $subsBase ?>"
                   class="category-btn <?= !$selectedCategory ? 'active' : '' ?>"
                   <?= !$selectedCategory ? 'style="background-color: #FFDBBB;"' : '' ?>>All</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= $subsBase ?>&amp;category=<?= urlencode($cat) ?>"
                       class="category-btn <?= $selectedCategory === $cat ? 'active' : '' ?>"
                       <?= $selectedCategory === $cat ? 'style="background-color: #FFDBBB;"' : '' ?>><?= htmlspecialchars($cat) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" id="mspToggleAdd">+ Add subscription</button>
        <form method="post" action="<?= getBasePath() ?>/index.php?action=rebuild_email_subscriptions">
            <button type="submit" class="btn btn-secondary" title="Re-scan stored messages and rebuild counts">Full resync</button>
        </form>
    </div>
</div>

<!-- List -->
<?php if (empty($subscriptions)): ?>
    <div class="empty-state">
        <?php if ($showRemoved): ?>
            <p>No removed subscriptions.</p>
        <?php elseif ($selectedCategory): ?>
            <p>No subscriptions in category "<?= htmlspecialchars($selectedCategory) ?>". <a href="<?= $subsBase ?>">View all</a></p>
        <?php else: ?>
            <p>No subscriptions yet. New senders are added automatically when mail arrives, or use <em>+ Add subscription</em> above.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="msp-list">
        <?php foreach ($subscriptions as $s): ?>
            <?php
                $ls = $s['last_seen_at'] ? date('d.m.Y', strtotime($s['last_seen_at'])) : '—';
                $matcher = $s['match_type'] === 'domain' ? '@' . $s['match_value'] : $s['match_value'];
                $isRemoved = !empty($s['removed_at']);
                $isPaused  = !empty($s['disabled']);
                $rowClass  = 'msp-item';
                if ($isRemoved) { $rowClass .= ' removed'; }
                elseif ($isPaused) { $rowClass .= ' paused'; }
                $hasUnsub = !empty($s['unsubscribe_url']) || !empty($s['unsubscribe_mailto']);
            ?>
            <div class="<?= $rowClass ?>">
                <div class="msp-info">
                    <div class="msp-title">
                        <span><?= htmlspecialchars($s['display_name'] ?: $s['match_value']) ?></span>
                        <?php if (!empty($s['category']) && $s['category'] !== 'unsortiert'): ?>
                            <span class="msp-chip cat"><?= htmlspecialchars($s['category']) ?></span>
                        <?php endif; ?>
                        <?php if ($isPaused && !$isRemoved): ?>
                            <span class="msp-chip paused">paused</span>
                        <?php endif; ?>
                        <?php if (!empty($s['unsubscribe_one_click'])): ?>
                            <span class="msp-chip oneclick">1-click unsubscribe</span>
                        <?php endif; ?>
                        <?php if (!empty($s['auto_detected'])): ?>
                            <span class="msp-chip auto" title="Added automatically from incoming mail">auto</span>
                        <?php endif; ?>
                    </div>
                    <div class="msp-matcher"><?= htmlspecialchars($matcher) ?></div>
                    <div class="msp-meta">
                        <span><strong><?= (int)$s['item_count'] ?></strong> item<?= (int)$s['item_count'] === 1 ? '' : 's' ?></span>
                        <span>last seen <strong><?= htmlspecialchars($ls) ?></strong></span>
                        <?php if ($isRemoved && !empty($s['removed_at'])): ?>
                            <span>removed <strong><?= date('d.m.Y', strtotime($s['removed_at'])) ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isRemoved): ?>
                        <div class="msp-edit" id="msp-edit-<?= (int)$s['id'] ?>">
                            <form method="post" action="<?= getBasePath() ?>/index.php?action=edit_email_subscription">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <div class="form-row">
                                    <div>
                                        <label>Display name</label>
                                        <input type="text" name="display_name" value="<?= htmlspecialchars($s['display_name']) ?>">
                                    </div>
                                    <div>
                                        <label>Category</label>
                                        <input type="text" name="category" value="<?= htmlspecialchars($s['category'] ?? '') ?>" placeholder="unsortiert">
                                    </div>
                                    <div>
                                        <button type="submit" class="btn">Save</button>
                                        <button type="button" class="btn btn-secondary" data-msp-close-edit="<?= (int)$s['id'] ?>">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="msp-actions">
                    <?php if ($isRemoved): ?>
                        <form method="post" action="<?= getBasePath() ?>/index.php?action=restore_email_subscription">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-success">Restore</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" data-msp-open-edit="<?= (int)$s['id'] ?>">Edit</button>
                        <form method="post" action="<?= getBasePath() ?>/index.php?action=toggle_email_subscription">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn <?= $isPaused ? 'btn-success' : 'btn-warning' ?>"><?= $isPaused ? 'Resume' : 'Pause' ?></button>
                        </form>
                        <?php if ($hasUnsub): ?>
                            <a href="<?= getBasePath() ?>/index.php?action=unsubscribe_email_subscription&amp;id=<?= (int)$s['id'] ?>" class="btn btn-secondary">Unsubscribe</a>
                        <?php endif; ?>
                        <form method="post" action="<?= getBasePath() ?>/index.php?action=delete_email_subscription" onsubmit="return confirm('Remove this subscription? Future mail from this source will be hidden.');">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-danger">Remove</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(function() {
    var addBtn  = document.getElementById('mspToggleAdd');
    var addForm = document.getElementById('mspAddForm');
    if (addBtn && addForm) {
        addBtn.addEventListener('click', function() {
            addForm.classList.toggle('open');
            if (addForm.classList.contains('open')) {
                var first = addForm.querySelector('input[name="match_value"]');
                if (first) first.focus();
            }
        });
    }

    document.addEventListener('click', function(e) {
        var openBtn = e.target.closest('[data-msp-open-edit]');
        if (openBtn) {
            var id = openBtn.getAttribute('data-msp-open-edit');
            var panel = document.getElementById('msp-edit-' + id);
            if (panel) panel.classList.toggle('open');
            return;
        }
        var closeBtn = e.target.closest('[data-msp-close-edit]');
        if (closeBtn) {
            var id2 = closeBtn.getAttribute('data-msp-close-edit');
            var panel2 = document.getElementById('msp-edit-' + id2);
            if (panel2) panel2.classList.remove('open');
        }
    });
})();
</script>
