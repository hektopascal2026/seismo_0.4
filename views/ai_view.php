<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Seismo AI Readable Feed</title>
    <style>
        body { font-family: monospace; line-height: 1.5; padding: 20px; background: #fff; color: #000; }
        .filter-summary { background: #f0f0f0; border: 2px solid #000; padding: 10px 14px; margin-bottom: 30px; font-size: 12px; }
        .filter-summary strong { text-transform: uppercase; }
        .entry { margin-bottom: 40px; border-bottom: 2px solid #000; padding-bottom: 20px; }
        .entry.priority { border-left: 4px solid #000; padding-left: 12px; }
        .meta { background: #eee; padding: 2px 5px; font-weight: bold; }
        .score-tag { display: inline-block; padding: 1px 6px; font-size: 11px; font-weight: bold; margin-left: 8px; }
        .score-investigation { background: #ff6b6b; color: #fff; }
        .score-important { background: #ffa726; color: #fff; }
        .score-background { background: #aaa; color: #fff; }
        .score-noise { background: #ddd; color: #666; }
        .priority-tag { display: inline-block; background: #000; color: #fff; padding: 1px 6px; font-size: 11px; font-weight: bold; margin-left: 8px; }
        .content-box {
            margin-top: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-left: 3px solid #ccc;
            white-space: pre-wrap;
        }
        .label { font-weight: bold; text-transform: uppercase; color: #444; }
        .back-link { display: inline-block; margin-bottom: 16px; font-size: 12px; color: #000; }
    </style>
</head>
<body>
    <a href="?action=beta" class="back-link">&larr; Back to generator</a>

    <?php if (isset($aiFilterSummary)): ?>
    <div class="filter-summary">
        <strong>Filters applied:</strong>
        Sources: <?= htmlspecialchars(implode(', ', $aiFilterSummary['sources'])) ?> |
        Date: <?= htmlspecialchars($aiFilterSummary['since']) ?> |
        Labels: <?= htmlspecialchars(implode(', ', $aiFilterSummary['labels'])) ?>
        <?php if ($aiFilterSummary['min_score'] !== null): ?> | Min score: <?= $aiFilterSummary['min_score'] ?>%<?php endif; ?>
        <?php if (!empty($aiFilterSummary['keywords'])): ?> | Keywords: <?= htmlspecialchars($aiFilterSummary['keywords']) ?><?php endif; ?>
        | Limit: <?= $aiFilterSummary['limit'] ?>
        | <strong>Showing <?= $aiFilterSummary['total'] ?> entries</strong>
    </div>
    <?php endif; ?>

    <?php foreach ($allItems as $item): ?>
        <div class="entry<?= !empty($item['priority']) ? ' priority' : '' ?>">
            <div class="meta">
                <?= $item['date'] ? date('Y-m-d H:i', $item['date']) : 'n/a' ?> | SOURCE: <?= htmlspecialchars($item['source']) ?>
                <?php if ($item['score'] !== null): ?>
                    <?php
                        $scoreClass = '';
                        if ($item['label'] === 'investigation_lead') $scoreClass = 'score-investigation';
                        elseif ($item['label'] === 'important') $scoreClass = 'score-important';
                        elseif ($item['label'] === 'background') $scoreClass = 'score-background';
                        elseif ($item['label'] === 'noise') $scoreClass = 'score-noise';
                    ?>
                    <span class="score-tag <?= $scoreClass ?>">SCORE: <?= round($item['score'] * 100) ?>% <?= strtoupper(str_replace('_', ' ', $item['label'] ?? '')) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['priority'])): ?>
                    <span class="priority-tag">PRIORITY</span>
                <?php endif; ?>
            </div>

            <div style="margin-top:10px;">
                <span class="label">Subject/Title:</span> <?= htmlspecialchars($item['title']) ?>
            </div>

            <?php if (($item['link'] ?? '#') !== '#'): ?>
            <div>
                <span class="label">Link:</span> <?= htmlspecialchars($item['link']) ?>
            </div>
            <?php endif; ?>

            <div class="content-box">
                <span class="label">--- Full Text ---</span><br>
                <?= htmlspecialchars($item['content']) ?>
            </div>
        </div>
    <?php endforeach; ?>
</body>
</html>
