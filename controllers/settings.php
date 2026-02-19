<?php
/**
 * Settings Controller
 *
 * Renders the settings page with all tabs (Basic, Script, Lex, Magnitu)
 * and the static pages (About, Beta, Styleguide).
 */

function handleSettingsPage($pdo) {
    $settingsTab = $_GET['tab'] ?? 'basic';
    
    $feedsStmt = $pdo->query("SELECT * FROM feeds WHERE source_type = 'rss' OR source_type IS NULL ORDER BY created_at DESC");
    $allFeeds = $feedsStmt->fetchAll();
    
    $substackFeedsStmt = $pdo->query("SELECT * FROM feeds WHERE source_type = 'substack' ORDER BY created_at DESC");
    $substackFeeds = $substackFeedsStmt->fetchAll();
    
    $tagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE category IS NOT NULL AND category != '' AND (source_type = 'rss' OR source_type IS NULL) ORDER BY category");
    $allTags = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $substackTagsStmt = $pdo->query("SELECT DISTINCT category FROM feeds WHERE source_type = 'substack' AND category IS NOT NULL AND category != '' ORDER BY category");
    $allSubstackTags = $substackTagsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $emailTagsStmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag");
    $allEmailTags = $emailTagsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $senderTags = [];
    try {
        $emailTableName = getEmailTableName($pdo);
        
        if ($emailTableName) {
            $descStmt = $pdo->query("DESCRIBE `$emailTableName`");
            $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $hasFromEmail = in_array('from_email', $tableColumns);
            $hasFromAddr = in_array('from_addr', $tableColumns);
            $hasFromName = in_array('from_name', $tableColumns);
            
            if ($hasFromEmail && $hasFromName) {
                $sendersStmt = $pdo->query("
                    SELECT DISTINCT 
                        from_email as email,
                        COALESCE(from_name, '') as name
                    FROM `$emailTableName`
                    WHERE from_email IS NOT NULL AND from_email != ''
                    ORDER BY from_email
                ");
            } elseif ($hasFromAddr) {
                $sendersStmt = $pdo->query("
                    SELECT DISTINCT 
                        from_addr as email,
                        '' as name
                    FROM `$emailTableName`
                    WHERE from_addr IS NOT NULL AND from_addr != ''
                    ORDER BY from_addr
                ");
            } else {
                $sendersStmt = null;
            }
            
            if ($sendersStmt) {
                $senders = $sendersStmt->fetchAll();
            } else {
                $senders = [];
            }
            
            $emailCol = $hasFromEmail ? 'from_email' : ($hasFromAddr ? 'from_addr' : null);
            $hasDateReceived = in_array('date_received', $tableColumns);
            $hasCreatedAt = in_array('created_at', $tableColumns);
            
            foreach ($senders as $sender) {
                $email = $sender['email'];
                $tagStmt = $pdo->prepare("SELECT tag, disabled, removed_at FROM sender_tags WHERE from_email = ?");
                $tagStmt->execute([$email]);
                $tagResult = $tagStmt->fetch();
                
                if (!$tagResult) {
                    $insertStmt = $pdo->prepare("INSERT INTO sender_tags (from_email, tag, disabled) VALUES (?, 'unclassified', 0)");
                    $insertStmt->execute([$email]);
                    $tagResult = ['tag' => 'unclassified', 'disabled' => 0, 'removed_at' => null];
                } elseif ($tagResult['removed_at'] && $emailCol) {
                    $dateCond = [];
                    if ($hasDateReceived) $dateCond[] = "date_received > ?";
                    if ($hasCreatedAt) $dateCond[] = "created_at > ?";
                    
                    if (!empty($dateCond)) {
                        $dateWhere = '(' . implode(' OR ', $dateCond) . ')';
                        $newerStmt = $pdo->prepare("
                            SELECT 1 FROM `$emailTableName`
                            WHERE `$emailCol` = ? AND $dateWhere
                            LIMIT 1
                        ");
                        $removedAt = $tagResult['removed_at'];
                        $params = [$email];
                        if ($hasDateReceived) $params[] = $removedAt;
                        if ($hasCreatedAt) $params[] = $removedAt;
                        $newerStmt->execute($params);
                        
                        if ($newerStmt->fetch()) {
                            $reactivateStmt = $pdo->prepare("UPDATE sender_tags SET removed_at = NULL WHERE from_email = ?");
                            $reactivateStmt->execute([$email]);
                            $tagResult['removed_at'] = null;
                        }
                    }
                }
                
                if (empty($tagResult['removed_at'])) {
                    $senderTags[] = [
                        'email' => $email,
                        'name' => $sender['name'],
                        'tag' => $tagResult['tag'],
                        'disabled' => (bool)$tagResult['disabled']
                    ];
                }
            }
        }
    } catch (PDOException $e) {}
    
    $lexConfig = getLexConfig();
    
    $magnituConfig = getAllMagnituConfig($pdo);
    $magnituScoreStats = ['total' => 0, 'magnitu' => 0, 'recipe' => 0];
    try {
        $magnituScoreStats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores")->fetchColumn();
        $magnituScoreStats['magnitu'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'magnitu'")->fetchColumn();
        $magnituScoreStats['recipe'] = (int)$pdo->query("SELECT COUNT(*) FROM entry_scores WHERE score_source = 'recipe'")->fetchColumn();
    } catch (PDOException $e) {}
    
    $scraperConfigs = [];
    try {
        $scraperConfigs = $pdo->query("SELECT * FROM scraper_configs ORDER BY created_at DESC")->fetchAll();
    } catch (PDOException $e) {}
    
    $mailConfig = [
        'imap_mailbox'    => getMagnituConfig($pdo, 'mail_imap_mailbox') ?: '',
        'imap_username'   => getMagnituConfig($pdo, 'mail_imap_username') ?: '',
        'imap_password'   => getMagnituConfig($pdo, 'mail_imap_password') ?: '',
        'max_messages'    => getMagnituConfig($pdo, 'mail_max_messages') ?: '50',
        'search_criteria' => getMagnituConfig($pdo, 'mail_search_criteria') ?: 'UNSEEN',
        'mark_seen'       => getMagnituConfig($pdo, 'mail_mark_seen') ?? '1',
        'db_table'        => getMagnituConfig($pdo, 'mail_db_table') ?: 'fetched_emails',
    ];
    $mailConfigured = !empty($mailConfig['imap_username']) && !empty($mailConfig['imap_password']);
    
    include 'views/settings.php';
}

// ---------------------------------------------------------------------------
// Static pages
// ---------------------------------------------------------------------------

function handleAboutPage($pdo) {
    $stats = [];
    try {
        $stats['feeds'] = $pdo->query("SELECT COUNT(*) FROM feeds WHERE source_type = 'rss' OR source_type IS NULL")->fetchColumn();
        $stats['feed_items'] = $pdo->query("SELECT COUNT(*) FROM feed_items")->fetchColumn();
        
        $emailTable = getEmailTableName($pdo);
        $stats['emails'] = $pdo->query("SELECT COUNT(*) FROM `$emailTable`")->fetchColumn();
        
        $stats['lex_eu'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'eu'")->fetchColumn();
        $stats['lex_ch'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch'")->fetchColumn();
        $stats['lex_de'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'de'")->fetchColumn();
        $stats['jus_bger'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bger'")->fetchColumn();
        $stats['jus_bge'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bge'")->fetchColumn();
        $stats['jus_bvger'] = $pdo->query("SELECT COUNT(*) FROM lex_items WHERE source = 'ch_bvger'")->fetchColumn();
        $stats['scraper_configs'] = $pdo->query("SELECT COUNT(*) FROM scraper_configs")->fetchColumn();
        $stats['scraper_items'] = $pdo->query("SELECT COUNT(*) FROM feed_items fi JOIN feeds f ON fi.feed_id = f.id WHERE f.source_type = 'scraper'")->fetchColumn();
    } catch (PDOException $e) {}
    $lastChangeDate = date('d.m.Y', filemtime(__DIR__ . '/../index.php'));
    include 'views/about.php';
}

function handleBetaPage() {
    $lastChangeDate = date('d.m.Y', filemtime(__DIR__ . '/../index.php'));
    include 'views/beta.php';
}

function handleStyleguidePage() {
    $lastChangeDate = date('d.m.Y', filemtime(__DIR__ . '/../index.php'));
    include 'views/styleguide.php';
}
