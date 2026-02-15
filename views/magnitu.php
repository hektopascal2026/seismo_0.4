<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magnitu – Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <span class="top-bar-title">
                    <a href="?action=index">
                        <svg class="logo-icon logo-icon-large" viewBox="0 0 24 16" xmlns="http://www.w3.org/2000/svg">
                            <rect width="24" height="16" fill="#FFFFC5"/>
                            <path d="M0,8 L4,12 L6,4 L10,10 L14,2 L18,8 L20,6 L24,8" stroke="#000000" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    Seismo
                </span>
                <span class="top-bar-subtitle">ein Prototyp von hektopascal.org | v0.3.2</span>
            </div>
            <div class="top-bar-actions">
                <a href="?action=refresh_all&from=magnitu" class="top-bar-btn" title="Refresh all sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></a>
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <!-- Navigation Drawer -->
        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link active">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=settings" class="nav-link">Settings</a>
            <a href="?action=about" class="nav-link">About</a>
            <a href="?action=beta" class="nav-link">Beta</a>
        </nav>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Magnitu Explainer -->
        <div class="magnitu-explainer">
            <h2>Magnitu</h2>
            <p>This page shows entries that the Magnitu relevance model considers worth your attention. <strong>Investigation leads</strong> are entries that could be the start of an investigative story. <strong>Important</strong> entries are significant developments you should be aware of. The model learns from your labels over time &mdash; the more you label, the sharper it gets.</p>
            <?php if (empty($investigationItems) && empty($importantItems)): ?>
                <p style="margin-top: 8px; opacity: 0.7;">No scored entries yet. Train a model in Magnitu and push scores to see results here.</p>
            <?php else: ?>
                <p style="margin-top: 4px; font-size: 13px; opacity: 0.6;"><?= count($investigationItems) ?> investigation lead<?= count($investigationItems) !== 1 ? 's' : '' ?> · <?= count($importantItems) ?> important · <?= $totalScored ?> total scored</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($investigationItems)): ?>
        <!-- Investigation Leads -->
        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">Investigation Leads</h2>
                <button class="btn btn-secondary entry-expand-all-btn" data-section="investigation">&#9660; expand all</button>
            </div>
            <?php foreach ($investigationItems as $itemWrapper): ?>
                <?php
                    $entryScore = $itemWrapper['score'] ?? null;
                    $relevanceScore = $entryScore ? (float)$entryScore['relevance_score'] : null;
                    $predictedLabel = $entryScore['predicted_label'] ?? null;
                    $scoreExplanation = $entryScore ? json_decode($entryScore['explanation'] ?? '{}', true) : null;
                ?>
                <?php if ($itemWrapper['type'] === 'feed' || $itemWrapper['type'] === 'substack'): ?>
                    <?php $item = $itemWrapper['data']; ?>
                    <?php
                        $fullContent = strip_tags($item['content'] ?: $item['description']);
                        $contentPreview = mb_substr($fullContent, 0, 200);
                        if (mb_strlen($fullContent) > 200) $contentPreview .= '...';
                        $hasMore = mb_strlen($fullContent) > 200;
                        $feedTagColor = ($itemWrapper['type'] === 'substack') ? 'background-color: #C5B4D1;' : 'background-color: #add8e6;';
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if (!empty($item['feed_category']) && $item['feed_category'] !== 'unsortiert'): ?>
                                <span class="entry-tag" style="<?= $feedTagColor ?>"><?= htmlspecialchars($item['feed_category']) ?></span>
                            <?php endif; ?>
                            <?php if ($relevanceScore !== null): ?>
                                <span class="magnitu-badge magnitu-badge-investigation" title="Investigation lead (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title">
                            <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($item['title']) ?></a>
                        </h3>
                        <?php if ($item['description'] || $item['content']): ?>
                            <div class="entry-content entry-preview">
                                <?= htmlspecialchars($contentPreview) ?>
                                <?php if ($item['link']): ?>
                                    <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener" class="entry-link" style="margin-left: 4px;">Read more &rarr;</a>
                                <?php endif; ?>
                            </div>
                            <div class="entry-full-content" style="display:none"><?= htmlspecialchars($fullContent) ?></div>
                        <?php endif; ?>
                        <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                            <div class="magnitu-explanation" style="display:none;">
                                <div class="magnitu-explanation-label">Magnitu: <?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>% confidence)</div>
                                <div class="magnitu-explanation-features">
                                    <?php foreach ($scoreExplanation['top_features'] as $feat): ?>
                                        <span class="magnitu-feature <?= ($feat['direction'] ?? 'positive') === 'positive' ? 'magnitu-feature-positive' : 'magnitu-feature-negative' ?>">
                                            <?= htmlspecialchars($feat['feature']) ?> <?= ($feat['direction'] ?? 'positive') === 'positive' ? '+' : '' ?><?= round($feat['weight'], 2) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($hasMore): ?>
                                    <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                                <?php endif; ?>
                                <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                                    <button class="btn btn-secondary magnitu-why-btn" style="font-size: 11px; padding: 3px 8px;">Why?</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['published_date']): ?>
                                <span class="entry-date"><?= date('d.m.Y H:i', strtotime($item['published_date'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($itemWrapper['type'] === 'lex'): ?>
                    <?php $lexItem = $itemWrapper['data']; ?>
                    <?php
                        $lexSource = $lexItem['source'] ?? 'eu';
                        $lexIsEu = ($lexSource === 'eu');
                        $lexSourceEmoji = $lexIsEu ? '🇪🇺' : '🇨🇭';
                        $lexSourceLabel = $lexIsEu ? 'EU' : 'CH';
                        $lexDocType = $lexItem['document_type'] ?? 'Legislation';
                        $lexUrl = $lexItem['eurlex_url'] ?? '#';
                        $lexDate = $lexItem['document_date'] ? date('d.m.Y', strtotime($lexItem['document_date'])) : '';
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <span class="entry-tag" style="background-color: #f5f562; border-color: #000000;"><?= $lexSourceEmoji ?> <?= $lexSourceLabel ?></span>
                            <span class="entry-tag" style="background-color: #f5f5f5;"><?= htmlspecialchars($lexDocType) ?></span>
                            <?php if ($relevanceScore !== null): ?>
                                <span class="magnitu-badge magnitu-badge-investigation" title="Investigation lead (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title">
                            <a href="<?= htmlspecialchars($lexUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($lexItem['title']) ?></a>
                        </h3>
                        <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                            <div class="magnitu-explanation" style="display:none;">
                                <div class="magnitu-explanation-label">Magnitu: <?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>% confidence)</div>
                                <div class="magnitu-explanation-features">
                                    <?php foreach ($scoreExplanation['top_features'] as $feat): ?>
                                        <span class="magnitu-feature <?= ($feat['direction'] ?? 'positive') === 'positive' ? 'magnitu-feature-positive' : 'magnitu-feature-negative' ?>">
                                            <?= htmlspecialchars($feat['feature']) ?> <?= ($feat['direction'] ?? 'positive') === 'positive' ? '+' : '' ?><?= round($feat['weight'], 2) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-family: monospace;"><?= htmlspecialchars($lexItem['celex'] ?? '') ?></span>
                                <a href="<?= htmlspecialchars($lexUrl) ?>" target="_blank" rel="noopener" class="entry-link"><?= $lexIsEu ? 'EUR-Lex &rarr;' : 'Fedlex &rarr;' ?></a>
                                <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                                    <button class="btn btn-secondary magnitu-why-btn" style="font-size: 11px; padding: 3px 8px;">Why?</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($lexDate): ?>
                                <span class="entry-date"><?= $lexDate ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php $email = $itemWrapper['data']; ?>
                    <?php
                        $dateValue = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? $email['date_sent'] ?? null;
                        $createdAt = $dateValue ? date('d.m.Y H:i', strtotime($dateValue)) : '';
                        $fromName = trim((string)($email['from_name'] ?? ''));
                        $fromEmail = trim((string)($email['from_email'] ?? ''));
                        $fromDisplay = $fromName !== '' ? $fromName : ($fromEmail !== '' ? $fromEmail : 'Unknown sender');
                        $subject = trim((string)($email['subject'] ?? ''));
                        if ($subject === '') $subject = '(No subject)';
                        $body = (string)($email['text_body'] ?? '');
                        if ($body === '') $body = strip_tags((string)($email['html_body'] ?? ''));
                        $body = trim(preg_replace('/\s+/', ' ', $body ?? ''));
                        $bodyPreview = mb_substr($body, 0, 200);
                        if (mb_strlen($body) > 200) $bodyPreview .= '...';
                        $hasMore = mb_strlen($body) > 200;
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if (!empty($email['sender_tag']) && $email['sender_tag'] !== 'unclassified'): ?>
                                <span class="entry-tag" style="background-color: #FFDBBB;"><?= htmlspecialchars($email['sender_tag']) ?></span>
                            <?php endif; ?>
                            <?php if ($relevanceScore !== null): ?>
                                <span class="magnitu-badge magnitu-badge-investigation" title="Investigation lead (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title"><?= htmlspecialchars($subject) ?></h3>
                        <div class="entry-content entry-preview"><?= htmlspecialchars($bodyPreview) ?></div>
                        <div class="entry-full-content" style="display:none"><?= htmlspecialchars($body) ?></div>
                        <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                            <div class="magnitu-explanation" style="display:none;">
                                <div class="magnitu-explanation-label">Magnitu: <?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>% confidence)</div>
                                <div class="magnitu-explanation-features">
                                    <?php foreach ($scoreExplanation['top_features'] as $feat): ?>
                                        <span class="magnitu-feature <?= ($feat['direction'] ?? 'positive') === 'positive' ? 'magnitu-feature-positive' : 'magnitu-feature-negative' ?>">
                                            <?= htmlspecialchars($feat['feature']) ?> <?= ($feat['direction'] ?? 'positive') === 'positive' ? '+' : '' ?><?= round($feat['weight'], 2) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($hasMore): ?>
                                    <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                                <?php endif; ?>
                                <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                                    <button class="btn btn-secondary magnitu-why-btn" style="font-size: 11px; padding: 3px 8px;">Why?</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($createdAt): ?>
                                <span class="entry-date"><?= htmlspecialchars($createdAt) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($importantItems)): ?>
        <!-- Important Entries -->
        <div class="latest-entries-section">
            <div class="section-title-row">
                <h2 class="section-title">Important</h2>
                <button class="btn btn-secondary entry-expand-all-btn" data-section="important">&#9660; expand all</button>
            </div>
            <?php foreach ($importantItems as $itemWrapper): ?>
                <?php
                    $entryScore = $itemWrapper['score'] ?? null;
                    $relevanceScore = $entryScore ? (float)$entryScore['relevance_score'] : null;
                    $predictedLabel = $entryScore['predicted_label'] ?? null;
                    $scoreExplanation = $entryScore ? json_decode($entryScore['explanation'] ?? '{}', true) : null;
                ?>
                <?php if ($itemWrapper['type'] === 'feed' || $itemWrapper['type'] === 'substack'): ?>
                    <?php $item = $itemWrapper['data']; ?>
                    <?php
                        $fullContent = strip_tags($item['content'] ?: $item['description']);
                        $contentPreview = mb_substr($fullContent, 0, 200);
                        if (mb_strlen($fullContent) > 200) $contentPreview .= '...';
                        $hasMore = mb_strlen($fullContent) > 200;
                        $feedTagColor = ($itemWrapper['type'] === 'substack') ? 'background-color: #C5B4D1;' : 'background-color: #add8e6;';
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if (!empty($item['feed_category']) && $item['feed_category'] !== 'unsortiert'): ?>
                                <span class="entry-tag" style="<?= $feedTagColor ?>"><?= htmlspecialchars($item['feed_category']) ?></span>
                            <?php endif; ?>
                            <?php if ($relevanceScore !== null): ?>
                                <span class="magnitu-badge magnitu-badge-important" title="Important (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title">
                            <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($item['title']) ?></a>
                        </h3>
                        <?php if ($item['description'] || $item['content']): ?>
                            <div class="entry-content entry-preview">
                                <?= htmlspecialchars($contentPreview) ?>
                                <?php if ($item['link']): ?>
                                    <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener" class="entry-link" style="margin-left: 4px;">Read more &rarr;</a>
                                <?php endif; ?>
                            </div>
                            <div class="entry-full-content" style="display:none"><?= htmlspecialchars($fullContent) ?></div>
                        <?php endif; ?>
                        <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                            <div class="magnitu-explanation" style="display:none;">
                                <div class="magnitu-explanation-label">Magnitu: <?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>% confidence)</div>
                                <div class="magnitu-explanation-features">
                                    <?php foreach ($scoreExplanation['top_features'] as $feat): ?>
                                        <span class="magnitu-feature <?= ($feat['direction'] ?? 'positive') === 'positive' ? 'magnitu-feature-positive' : 'magnitu-feature-negative' ?>">
                                            <?= htmlspecialchars($feat['feature']) ?> <?= ($feat['direction'] ?? 'positive') === 'positive' ? '+' : '' ?><?= round($feat['weight'], 2) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($hasMore): ?>
                                    <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                                <?php endif; ?>
                                <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                                    <button class="btn btn-secondary magnitu-why-btn" style="font-size: 11px; padding: 3px 8px;">Why?</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['published_date']): ?>
                                <span class="entry-date"><?= date('d.m.Y H:i', strtotime($item['published_date'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($itemWrapper['type'] === 'lex'): ?>
                    <?php $lexItem = $itemWrapper['data']; ?>
                    <?php
                        $lexSource = $lexItem['source'] ?? 'eu';
                        $lexIsEu = ($lexSource === 'eu');
                        $lexSourceEmoji = $lexIsEu ? '🇪🇺' : '🇨🇭';
                        $lexSourceLabel = $lexIsEu ? 'EU' : 'CH';
                        $lexDocType = $lexItem['document_type'] ?? 'Legislation';
                        $lexUrl = $lexItem['eurlex_url'] ?? '#';
                        $lexDate = $lexItem['document_date'] ? date('d.m.Y', strtotime($lexItem['document_date'])) : '';
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <span class="entry-tag" style="background-color: #f5f562; border-color: #000000;"><?= $lexSourceEmoji ?> <?= $lexSourceLabel ?></span>
                            <span class="entry-tag" style="background-color: #f5f5f5;"><?= htmlspecialchars($lexDocType) ?></span>
                            <?php if ($relevanceScore !== null): ?>
                                <span class="magnitu-badge magnitu-badge-important" title="Important (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title">
                            <a href="<?= htmlspecialchars($lexUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($lexItem['title']) ?></a>
                        </h3>
                        <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                            <div class="magnitu-explanation" style="display:none;">
                                <div class="magnitu-explanation-label">Magnitu: <?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>% confidence)</div>
                                <div class="magnitu-explanation-features">
                                    <?php foreach ($scoreExplanation['top_features'] as $feat): ?>
                                        <span class="magnitu-feature <?= ($feat['direction'] ?? 'positive') === 'positive' ? 'magnitu-feature-positive' : 'magnitu-feature-negative' ?>">
                                            <?= htmlspecialchars($feat['feature']) ?> <?= ($feat['direction'] ?? 'positive') === 'positive' ? '+' : '' ?><?= round($feat['weight'], 2) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-family: monospace;"><?= htmlspecialchars($lexItem['celex'] ?? '') ?></span>
                                <a href="<?= htmlspecialchars($lexUrl) ?>" target="_blank" rel="noopener" class="entry-link"><?= $lexIsEu ? 'EUR-Lex &rarr;' : 'Fedlex &rarr;' ?></a>
                                <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                                    <button class="btn btn-secondary magnitu-why-btn" style="font-size: 11px; padding: 3px 8px;">Why?</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($lexDate): ?>
                                <span class="entry-date"><?= $lexDate ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php $email = $itemWrapper['data']; ?>
                    <?php
                        $dateValue = $email['date_received'] ?? $email['date_utc'] ?? $email['created_at'] ?? $email['date_sent'] ?? null;
                        $createdAt = $dateValue ? date('d.m.Y H:i', strtotime($dateValue)) : '';
                        $fromName = trim((string)($email['from_name'] ?? ''));
                        $fromEmail = trim((string)($email['from_email'] ?? ''));
                        $fromDisplay = $fromName !== '' ? $fromName : ($fromEmail !== '' ? $fromEmail : 'Unknown sender');
                        $subject = trim((string)($email['subject'] ?? ''));
                        if ($subject === '') $subject = '(No subject)';
                        $body = (string)($email['text_body'] ?? '');
                        if ($body === '') $body = strip_tags((string)($email['html_body'] ?? ''));
                        $body = trim(preg_replace('/\s+/', ' ', $body ?? ''));
                        $bodyPreview = mb_substr($body, 0, 200);
                        if (mb_strlen($body) > 200) $bodyPreview .= '...';
                        $hasMore = mb_strlen($body) > 200;
                    ?>
                    <div class="entry-card">
                        <div class="entry-header">
                            <?php if (!empty($email['sender_tag']) && $email['sender_tag'] !== 'unclassified'): ?>
                                <span class="entry-tag" style="background-color: #FFDBBB;"><?= htmlspecialchars($email['sender_tag']) ?></span>
                            <?php endif; ?>
                            <?php if ($relevanceScore !== null): ?>
                                <span class="magnitu-badge magnitu-badge-important" title="Important (<?= round($relevanceScore * 100) ?>%)"><?= round($relevanceScore * 100) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="entry-title"><?= htmlspecialchars($subject) ?></h3>
                        <div class="entry-content entry-preview"><?= htmlspecialchars($bodyPreview) ?></div>
                        <div class="entry-full-content" style="display:none"><?= htmlspecialchars($body) ?></div>
                        <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                            <div class="magnitu-explanation" style="display:none;">
                                <div class="magnitu-explanation-label">Magnitu: <?= htmlspecialchars($predictedLabel ?? '') ?> (<?= round($relevanceScore * 100) ?>% confidence)</div>
                                <div class="magnitu-explanation-features">
                                    <?php foreach ($scoreExplanation['top_features'] as $feat): ?>
                                        <span class="magnitu-feature <?= ($feat['direction'] ?? 'positive') === 'positive' ? 'magnitu-feature-positive' : 'magnitu-feature-negative' ?>">
                                            <?= htmlspecialchars($feat['feature']) ?> <?= ($feat['direction'] ?? 'positive') === 'positive' ? '+' : '' ?><?= round($feat['weight'], 2) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="entry-actions">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($hasMore): ?>
                                    <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                                <?php endif; ?>
                                <?php if ($scoreExplanation && !empty($scoreExplanation['top_features'])): ?>
                                    <button class="btn btn-secondary magnitu-why-btn" style="font-size: 11px; padding: 3px 8px;">Why?</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($createdAt): ?>
                                <span class="entry-date"><?= htmlspecialchars($createdAt) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <script>
    (function() {
        function collapseEntry(card, btn) {
            var preview = card.querySelector('.entry-preview');
            var full = card.querySelector('.entry-full-content');
            if (!preview || !full) return;
            full.style.display = 'none';
            preview.style.display = '';
            if (btn) btn.textContent = '\u25BC expand';
        }

        function expandEntry(card, btn) {
            var preview = card.querySelector('.entry-preview');
            var full = card.querySelector('.entry-full-content');
            if (!preview || !full) return;
            preview.style.display = 'none';
            full.style.display = 'block';
            if (btn) btn.textContent = '\u25B2 collapse';
        }

        // Magnitu "Why?" toggle
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.magnitu-why-btn');
            if (!btn) return;
            var card = btn.closest('.entry-card');
            var explanation = card.querySelector('.magnitu-explanation');
            if (!explanation) return;
            if (explanation.style.display === 'block') {
                explanation.style.display = 'none';
                btn.textContent = 'Why?';
            } else {
                explanation.style.display = 'block';
                btn.textContent = 'Hide';
            }
        });

        // Per-entry toggle
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.entry-expand-btn');
            if (!btn) return;
            var card = btn.closest('.entry-card');
            var full = card.querySelector('.entry-full-content');
            if (!full) return;
            if (full.style.display === 'block') {
                collapseEntry(card, btn);
            } else {
                expandEntry(card, btn);
            }
        });

        // Section expand-all toggle
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.entry-expand-all-btn');
            if (!btn) return;
            var section = btn.closest('.latest-entries-section');
            if (!section) return;
            var isExpanded = btn.dataset.expanded === 'true';
            section.querySelectorAll('.entry-card').forEach(function(card) {
                var entryBtn = card.querySelector('.entry-expand-btn');
                if (isExpanded) {
                    collapseEntry(card, entryBtn);
                } else {
                    expandEntry(card, entryBtn);
                }
            });
            btn.dataset.expanded = !isExpanded;
            btn.textContent = !isExpanded ? '\u25B2 collapse all' : '\u25BC expand all';
        });
    })();
    </script>
    <script>
    // Top bar toggle
    (function() {
        var menuBtn = document.getElementById('menuToggle');
        var navDrawer = document.getElementById('navDrawer');
        menuBtn.addEventListener('click', function() {
            var isOpen = navDrawer.classList.toggle('open');
            menuBtn.classList.toggle('active', isOpen);
        });
    })();
    </script>
</body>
</html>
