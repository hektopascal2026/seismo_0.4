<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI View - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
    <style>
        .ai-card { background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 40px; padding: 20px; }
        .ai-header { border-bottom: 2px solid #eee; margin-bottom: 15px; padding-bottom: 10px; }
        .ai-meta { font-family: monospace; font-size: 12px; color: #000000; }
        .ai-subject { font-size: 18px; font-weight: bold; color: #000; margin-top: 5px; }
        .ai-body { 
            white-space: pre-wrap; 
            font-family: "Courier New", Courier, monospace; 
            font-size: 14px; 
            line-height: 1.5; 
            background: #fdfdfd; 
            padding: 15px; 
            border: 1px solid #f0f0f0;
        }
        .ai-divider { border: 0; height: 1px; background: #333; margin: 60px 0; }
    </style>
</head>
<body>
    <div class="container">
        <nav class="main-nav">
            <a href="?action=index" class="nav-link">Seismo</a>
            <a href="?action=mail" class="nav-link">Back to Mail</a>
        </nav>

        <header>
            <h1>AI Extraction Feed</h1>
            <p class="subtitle">Readable by humans, optimized for AI processing.</p>
        </header>

        <?php if (!empty($emails)): ?>
            <?php foreach ($emails as $email): ?>
                <?php
                    // Map your DB columns to standard display variables
                    $sender = $email['from_addr'] ?? $email['from_email'] ?? 'Unknown';
                    $date = $email['date_utc'] ?? $email['created_at'] ?? 'N/A';
                    $subject = $email['subject'] ?? '(No Subject)';
                    
                    // Extract body
                    $body = !empty($email['body_text']) ? $email['body_text'] : ($email['text_body'] ?? '');
                    if (empty(trim($body))) {
                        $html = $email['body_html'] ?? $email['html_body'] ?? '';
                        $body = strip_tags($html);
                    }
                ?>
                <article class="ai-card">
                    <div class="ai-header">
                        <div class="ai-meta">
                            FROM: <?= htmlspecialchars($sender) ?><br>
                            DATE: <?= htmlspecialchars($date) ?>
                        </div>
                        <div class="ai-subject">SUBJECT: <?= htmlspecialchars($subject) ?></div>
                    </div>
                    <div class="ai-body"><?= htmlspecialchars(trim($body)) ?></div>
                </article>
                <hr class="ai-divider">
            <?php endforeach; ?>
        <?php else: ?>
            <p>No emails found in the current database table.</p>
        <?php endif; ?>
    </div>
</body>
</html>
