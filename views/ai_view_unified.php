<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Seismo Unified Ai Readable Feed</title>
    <style>
        body { font-family: monospace; line-height: 1.5; padding: 20px; background: #fff; color: #000; }
        .entry { margin-bottom: 40px; border-bottom: 2px solid #000; padding-bottom: 20px; }
        .meta { background: #eee; padding: 2px 5px; font-weight: bold; }
        .content-box { 
            margin-top: 15px; 
            padding: 10px; 
            background: #f9f9f9; 
            border-left: 3px solid #ccc;
            white-space: pre-wrap; /* Maintains line breaks for human readability */
        }
        .label { font-weight: bold; text-transform: uppercase; color: #444; }
    </style>
</head>
<body>
    <?php foreach ($allItems as $item): ?>
        <div class="entry">
            <div class="meta">
                <?= date('Y-m-d H:i', $item['date']) ?> | SOURCE: <?= htmlspecialchars($item['source']) ?>
            </div>
            
            <div style="margin-top:10px;">
                <span class="label">Subject/Title:</span> <?= htmlspecialchars($item['title']) ?>
            </div>

            <?php if ($item['link'] !== '#'): ?>
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
