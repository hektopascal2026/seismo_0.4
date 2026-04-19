<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Unsubscribe</h1>
        <p><strong><?= htmlspecialchars($sub['display_name']) ?></strong>
            <?php if ($sub['match_type'] === 'domain'): ?>
                <br><small>@<?= htmlspecialchars($sub['match_value']) ?></small>
            <?php else: ?>
                <br><small><?= htmlspecialchars($sub['match_value']) ?></small>
            <?php endif; ?>
        </p>

        <?php if (!empty($sub['unsubscribe_url'])): ?>
            <p>
                <a href="<?= htmlspecialchars($sub['unsubscribe_url']) ?>" target="_blank" rel="noopener" class="btn">Open provider unsubscribe page</a>
            </p>
        <?php endif; ?>

        <?php if (!empty($sub['unsubscribe_mailto'])): ?>
            <p>
                <a href="<?= htmlspecialchars($sub['unsubscribe_mailto']) ?>" class="btn btn-secondary">Open mailto unsubscribe</a>
            </p>
        <?php endif; ?>

        <?php if (!empty($sub['unsubscribe_one_click']) && !empty($sub['unsubscribe_url'])): ?>
            <form method="post" action="<?= getBasePath() ?>/index.php?action=unsubscribe_email_subscription&id=<?= (int)$sub['id'] ?>" style="margin: 1rem 0;">
                <input type="hidden" name="confirm_one_click" value="1">
                <button type="submit" class="btn">Send one-click unsubscribe (HTTPS, same domain)</button>
            </form>
        <?php endif; ?>

        <form method="post" action="<?= getBasePath() ?>/index.php?action=unsubscribe_email_subscription&id=<?= (int)$sub['id'] ?>" style="margin-top: 1rem;">
            <input type="hidden" name="mark_unsubscribed" value="1">
            <button type="submit" class="btn btn-secondary">Mark as unsubscribed in Seismo only (pause)</button>
        </form>

        <p style="margin-top: 2rem;"><a href="<?= getBasePath() ?>/index.php?action=mail_subscriptions">← Back to subscriptions</a></p>
    </div>
</body>
</html>
