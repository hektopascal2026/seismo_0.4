<?php
/**
 * views/partials/feeds_manage_panel.php
 *
 * Renders the RSS → Feeds management UI, inlined from the old Settings > Basic
 * block. Expects:
 *   $allFeeds  — array of feeds (RSS only)
 *   $allTags   — distinct RSS categories
 *   $lastRssRefreshDate (nullable)
 *
 * All form submissions carry from=feeds_manage so redirects come back here.
 */
$allTags = $allTags ?? [];
$allFeeds = $allFeeds ?? [];
?>
<style>
    .fmp-help {
        font-size: 12px;
        color: #555;
        margin: 0 0 12px 0;
        line-height: 1.5;
    }
    .fmp-help a { color: #000; }
    .fmp-add {
        margin-bottom: 18px;
    }
    .fmp-add .feed-input {
        padding: 8px 10px;
        border: 1px solid #000;
        background: #fff;
        font-size: 14px;
        min-width: 320px;
    }
    .fmp-tags-row {
        margin-bottom: 16px;
    }
    .fmp-tags-row h3 {
        margin: 0 0 6px 0;
        font-size: 13px;
    }
    .fmp-config-row {
        margin-top: 18px;
        padding-top: 16px;
        border-top: 2px solid #000;
    }
    .fmp-config-row h3 {
        margin: 0 0 6px 0;
        font-size: 13px;
    }
</style>

<p class="fmp-help">
    RSS feeds Seismo fetches. Add new URLs, tag them, enable/disable, or remove.
    <a href="<?= getBasePath() ?>/index.php?action=settings&amp;tab=feed_diagnostics">Feed diagnostics</a> — test every feed's HTTP + parse status.
</p>

<!-- Add feed -->
<div class="fmp-add">
    <form method="POST" action="<?= getBasePath() ?>/index.php?action=add_feed" enctype="multipart/form-data" class="add-feed-form" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="from" value="feeds_manage">
        <input type="url" name="url" placeholder="Enter RSS feed URL (https://example.com/feed.xml)" required class="feed-input">
        <button type="submit" class="btn btn-primary">Add feed</button>
    </form>
</div>

<!-- All tags (inline rename) -->
<?php if (!empty($allTags)): ?>
    <div class="fmp-tags-row">
        <h3>All tags</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($allTags as $tag): ?>
                <div class="feed-tag-input-wrapper" style="display: inline-flex;">
                    <input
                        type="text"
                        class="feed-tag-input all-tag-input"
                        value="<?= htmlspecialchars($tag) ?>"
                        data-original-tag="<?= htmlspecialchars($tag) ?>"
                        data-tag-name="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"
                        style="width: auto; min-width: 100px; padding: 6px 12px;"
                    >
                    <span class="feed-tag-indicator"></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Feeds list -->
<?php if (empty($allFeeds)): ?>
    <div class="empty-state">
        <p>No feeds added yet. Paste an RSS URL above to start.</p>
    </div>
<?php else: ?>
    <div class="settings-list">
        <?php foreach ($allFeeds as $feed): ?>
            <div class="settings-item">
                <div class="settings-item-info">
                    <div class="settings-item-title"><?= htmlspecialchars($feed['title']) ?></div>
                    <?php if (!empty($feed['description'])): ?>
                        <div class="settings-item-meta"><?= htmlspecialchars($feed['description']) ?></div>
                    <?php endif; ?>
                    <div class="settings-item-meta"><?= htmlspecialchars($feed['url']) ?></div>
                    <?php if ($feed['last_fetched']): ?>
                        <div class="settings-item-meta">Last updated: <?= date('d.m.Y H:i', strtotime($feed['last_fetched'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="settings-item-actions" style="flex-direction: column; align-items: flex-end; gap: 10px;">
                    <div style="display: flex; gap: 10px;">
                        <a href="?action=toggle_feed&amp;id=<?= (int)$feed['id'] ?>&amp;from=feeds_manage" class="btn <?= $feed['disabled'] ? 'btn-success' : 'btn-warning' ?>">
                            <?= $feed['disabled'] ? 'Enable' : 'Disable' ?>
                        </a>
                        <a href="?action=delete_feed&amp;id=<?= (int)$feed['id'] ?>&amp;from=feeds_manage"
                           class="btn btn-danger"
                           onclick="return confirm('Are you sure you want to delete this feed? This action cannot be undone.');">
                            Delete
                        </a>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600;">Tag:</label>
                        <div class="tag-input-wrapper">
                            <input
                                type="text"
                                class="tag-input feed-tag-input"
                                value="<?= htmlspecialchars($feed['category'] ?? 'unsortiert') ?>"
                                data-feed-id="<?= (int)$feed['id'] ?>"
                                data-original-tag="<?= htmlspecialchars($feed['category'] ?? 'unsortiert') ?>"
                                style="width: 150px;"
                            >
                            <span class="feed-tag-indicator"></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Config file -->
<div class="fmp-config-row">
    <h3>Config file</h3>
    <p style="font-size: 12px; margin: 0 0 10px 0;">
        Export your RSS feed list as JSON, or import a config to add/update feeds in bulk.
    </p>
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <a href="?action=download_rss_config" class="btn" style="text-decoration: none;">Download rss_feeds.json</a>
        <form method="POST" action="<?= getBasePath() ?>/index.php?action=upload_rss_config" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center; margin: 0;">
            <input type="file" name="rss_config_file" accept=".json,application/json" style="font-size: 12px; font-family: inherit;">
            <button type="submit" class="btn">Upload</button>
        </form>
    </div>
</div>

<!-- Inline tag-input JS (focused RSS-only version; Settings still carries the
     fuller version for Substack/Email tag management). -->
<script>
(function() {
    let allTags = <?= json_encode(array_values($allTags)) ?>;
    let suggestionList = null;

    function ensureList() {
        if (suggestionList) return suggestionList;
        suggestionList = document.createElement('ul');
        suggestionList.className = 'feed-tag-suggestions';
        suggestionList.style.display = 'none';
        document.body.appendChild(suggestionList);
        return suggestionList;
    }
    function hide() { if (suggestionList) suggestionList.style.display = 'none'; }
    function show(input, suggestions) {
        const list = ensureList();
        if (!suggestions.length) { list.style.display = 'none'; return; }
        list.innerHTML = '';
        suggestions.forEach(tag => {
            const li = document.createElement('li');
            li.textContent = tag;
            li.addEventListener('click', () => { input.value = tag; input.dispatchEvent(new Event('input')); hide(); });
            list.appendChild(li);
        });
        const rect = input.getBoundingClientRect();
        list.style.top = (rect.bottom + window.scrollY) + 'px';
        list.style.left = (rect.left + window.scrollX) + 'px';
        list.style.width = rect.width + 'px';
        list.style.display = 'block';
    }
    function filterTags(query) {
        if (!query || query === 'unsortiert') return [];
        const q = query.toLowerCase();
        return allTags.filter(t => t.toLowerCase().includes(q) && t !== query).slice(0, 5);
    }
    function isNewTag(tag) { return tag && tag !== 'unsortiert' && !allTags.includes(tag); }
    function updateIndicator(input, value) {
        const indicator = input.parentElement.querySelector('.feed-tag-indicator');
        if (!indicator) return;
        if (isNewTag(value)) { indicator.textContent = 'new'; indicator.className = 'feed-tag-indicator feed-tag-new'; }
        else { indicator.textContent = ''; indicator.className = 'feed-tag-indicator'; }
    }

    // Per-feed tag editor
    document.querySelectorAll('.feed-tag-input:not(.all-tag-input)').forEach(input => {
        input.addEventListener('focus', function() { const v = this.value.trim(); if (v && v !== 'unsortiert') show(this, filterTags(v)); updateIndicator(this, v); });
        input.addEventListener('input', function() { const v = this.value.trim(); updateIndicator(this, v); if (v && v !== 'unsortiert') show(this, filterTags(v)); else hide(); });
        input.addEventListener('blur',  function() { setTimeout(hide, 200); });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = this.value.trim();
                if (!value) { this.value = this.dataset.originalTag || 'unsortiert'; updateIndicator(this, this.value); hide(); return; }
                const fd = new FormData();
                fd.append('feed_id', this.dataset.feedId);
                fd.append('tag', value);
                this.classList.add('feed-tag-saving');
                fetch('?action=update_feed_tag', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            this.dataset.originalTag = value;
                            this.classList.remove('feed-tag-saving');
                            this.classList.add('feed-tag-saved');
                            setTimeout(() => this.classList.remove('feed-tag-saved'), 2000);
                            this.blur(); hide();
                            if (!allTags.includes(value)) { allTags.push(value); allTags.sort(); }
                        } else {
                            this.classList.remove('feed-tag-saving');
                            alert('Error: ' + (data.error || 'Failed to update tag'));
                        }
                    })
                    .catch(() => { this.classList.remove('feed-tag-saving'); alert('Error updating tag'); });
            } else if (e.key === 'Escape') {
                this.value = this.dataset.originalTag || 'unsortiert';
                updateIndicator(this, this.value); hide(); this.blur();
            }
        });
    });

    // All-tag rename
    document.querySelectorAll('.all-tag-input').forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const value = this.value.trim();
            const oldTag = this.dataset.tagName;
            if (!value) { this.value = this.dataset.originalTag; return; }
            if (value === oldTag) { this.blur(); return; }
            const fd = new FormData();
            fd.append('old_tag', oldTag);
            fd.append('new_tag', value);
            this.classList.add('feed-tag-saving');
            fetch('?action=rename_tag', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.dataset.originalTag = value;
                        this.dataset.tagName = value;
                        this.classList.remove('feed-tag-saving');
                        this.classList.add('feed-tag-saved');
                        setTimeout(() => this.classList.remove('feed-tag-saved'), 2000);
                        const i = allTags.indexOf(oldTag);
                        if (i !== -1) allTags.splice(i, 1);
                        if (!allTags.includes(value)) allTags.push(value);
                        allTags.sort();
                    } else {
                        this.classList.remove('feed-tag-saving');
                        alert('Error: ' + (data.error || 'Failed to rename tag'));
                        this.value = this.dataset.originalTag;
                    }
                })
                .catch(() => { this.classList.remove('feed-tag-saving'); alert('Error renaming tag'); });
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.feed-tag-input-wrapper') && !e.target.closest('.feed-tag-suggestions')) hide();
    });
})();
</script>
