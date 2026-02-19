<?php
/**
 * Mail Controller
 *
 * Handles the mail page, sender management, email search/retrieval,
 * mail fetcher config/script downloads, and the API tag endpoint.
 */

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

function handleMailPage($pdo) {
    $emails = [];
    $mailTableError = null;
    $lastMailRefreshDate = null;
    $showAll = isset($_GET['show_all']) || isset($_SESSION['email_refresh_count']);
    $limit = $showAll ? 500 : 50;
    
    $emailTagsStmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag");
    $emailTags = $emailTagsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $selectedEmailTag = $_GET['email_tag'] ?? null;
    
    $disabledStmt = $pdo->query("SELECT from_email FROM sender_tags WHERE disabled = 1 OR removed_at IS NOT NULL");
    $disabledEmails = $disabledStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $tableName = getEmailTableName($pdo);
    
    try {
        $foundTable = $tableName;
        
        if (!$foundTable) {
            $mailTableError = "No emails table found.";
        } else {
            $timestampColumns = ['created_at', 'date_utc', 'date_received', 'date_sent', 'timestamp', 'created'];
            $lastRefreshDate = null;
            
            foreach ($timestampColumns as $col) {
                try {
                    $lastMailRefreshStmt = $pdo->query("SELECT MAX(`$col`) AS last_refresh FROM `$tableName` WHERE `$col` IS NOT NULL");
                    $lastMailRefreshResult = $lastMailRefreshStmt->fetch();
                    if ($lastMailRefreshResult && $lastMailRefreshResult['last_refresh']) {
                        $lastRefreshDate = $lastMailRefreshResult['last_refresh'];
                        break;
                    }
                } catch (PDOException $e) {
                    continue;
                }
            }
            
            if ($lastRefreshDate) {
                $lastMailRefreshDate = date('d.m.Y H:i', strtotime($lastRefreshDate));
            }

            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
            $countResult = $countStmt->fetch();
            $emailCount = $countResult['count'] ?? 0;

            $descStmt = $pdo->query("DESCRIBE `$tableName`");
            $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            try {
                $isCronjobTable = in_array('from_addr', $tableColumns) && 
                                 (in_array('body_text', $tableColumns) || in_array('body_html', $tableColumns));
                
                if ($isCronjobTable) {
                    $selectClause = "
                        id,
                        subject,
                        from_addr as from_email,
                        from_addr as from_name,
                        date_utc as date_received,
                        date_utc as date_sent,
                        body_text as text_body,
                        body_html as html_body,
                        created_at
                    ";
                    $orderBy = "date_utc DESC";
                } else {
                    $selectColumns = [];
                    $columnMap = [
                        'id' => 'id',
                        'subject' => 'subject',
                        'from_email' => 'from_email',
                        'from_name' => 'from_name',
                        'created_at' => 'created_at',
                        'date_received' => 'date_received',
                        'date_sent' => 'date_sent',
                        'text_body' => 'text_body',
                        'html_body' => 'html_body'
                    ];
                    
                    foreach ($columnMap as $expected => $actual) {
                        if (in_array($actual, $tableColumns)) {
                            $selectColumns[] = "`$actual` as `$expected`";
                        }
                    }
                    
                    if (empty($selectColumns)) {
                        $selectClause = '*';
                    } else {
                        $selectClause = implode(', ', $selectColumns);
                    }
                    
                    $orderBy = 'id DESC';
                    foreach (['date_received', 'date_utc', 'date_sent', 'created_at', 'id'] as $orderCol) {
                        if (in_array($orderCol, $tableColumns)) {
                            $orderBy = "`$orderCol` DESC";
                            break;
                        }
                    }
                }
                
                $whereClause = "1=1";
                $params = [];
                
                if (!empty($disabledEmails)) {
                    $placeholders = implode(',', array_fill(0, count($disabledEmails), '?'));
                    if ($isCronjobTable) {
                        $whereClause = "from_addr NOT IN ($placeholders)";
                    } else {
                        $whereClause = "from_email NOT IN ($placeholders)";
                    }
                    $params = $disabledEmails;
                }
                
                if ($selectedEmailTag) {
                    $tagStmt = $pdo->prepare("SELECT from_email FROM sender_tags WHERE tag = ? AND removed_at IS NULL");
                    $tagStmt->execute([$selectedEmailTag]);
                    $taggedEmails = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($taggedEmails)) {
                        $tagPlaceholders = implode(',', array_fill(0, count($taggedEmails), '?'));
                        if ($isCronjobTable) {
                            $whereClause .= " AND from_addr IN ($tagPlaceholders)";
                        } else {
                            $whereClause .= " AND from_email IN ($tagPlaceholders)";
                        }
                        $params = array_merge($params, $taggedEmails);
                    } else {
                        $emails = [];
                        include 'views/mail.php';
                        return;
                    }
                }
                
                $sql = "SELECT $selectClause FROM `$tableName` WHERE $whereClause ORDER BY $orderBy LIMIT $limit";
                if (!empty($params)) {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } else {
                    $stmt = $pdo->query($sql);
                }
                $emails = $stmt->fetchAll();
                
                foreach ($emails as &$email) {
                    if (isset($email['from_email']) && isset($email['from_name']) && 
                        $email['from_email'] === $email['from_name'] && 
                        !empty($email['from_email'])) {
                        $fromAddr = $email['from_email'];
                        if (preg_match('/^"([^"]+)"\s*<(.+)>$/', $fromAddr, $matches)) {
                            $email['from_name'] = $matches[1];
                            $email['from_email'] = $matches[2];
                        } elseif (preg_match('/^(.+)\s*<(.+)>$/', $fromAddr, $matches)) {
                            $email['from_name'] = trim($matches[1]);
                            $email['from_email'] = $matches[2];
                        } elseif (preg_match('/^(.+@.+)$/', $fromAddr)) {
                            $email['from_email'] = $fromAddr;
                            $email['from_name'] = '';
                        }
                    }
                }
                unset($email);
                attachSenderTags($pdo, $emails);
                
                usort($emails, function($a, $b) {
                    $dateA = $a['date_received'] ?? $a['date_utc'] ?? $a['created_at'] ?? $a['date_sent'] ?? '';
                    $dateB = $b['date_received'] ?? $b['date_utc'] ?? $b['created_at'] ?? $b['date_sent'] ?? '';
                    $timeA = $dateA ? strtotime($dateA) : 0;
                    $timeB = $dateB ? strtotime($dateB) : 0;
                    return $timeB - $timeA;
                });
            } catch (PDOException $e) {
                try {
                    $stmt = $pdo->query("SELECT * FROM `$tableName` LIMIT $limit");
                    $emails = $stmt->fetchAll();
                    $mailTableError = "Warning: Using SELECT * query. Table columns: " . implode(', ', $tableColumns) . ". Original error: " . $e->getMessage();
                } catch (PDOException $e2) {
                    $mailTableError = "Query error: " . $e2->getMessage() . ". Table: $tableName, Columns: " . implode(', ', $tableColumns);
                    $emails = [];
                }
            }
            
            if ($emailCount > 0 && empty($emails)) {
                $mailTableError = "Found $emailCount email(s) in table '$tableName' but query returned no results. Table columns: " . implode(', ', $tableColumns);
            } elseif ($emailCount > 0 && count($emails) > 0) {
                if (isset($_SESSION['email_refresh_count'])) {
                    unset($_SESSION['email_refresh_count']);
                }
            }
        }
    } catch (PDOException $e) {
        $mailTableError = "Database error: " . $e->getMessage();
    }

    $lastChangeDate = date('d.m.Y', filemtime(__DIR__ . '/../index.php'));
    
    include 'views/mail.php';
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

function handleRefreshEmails($pdo) {
    refreshEmails($pdo);
    $currentAction = $_GET['from'] ?? 'mail';
    $redirectUrl = '?action=' . $currentAction . '&show_all=1';
    header('Location: ' . $redirectUrl);
    exit;
}

function handleSaveMailConfig($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mailFields = [
            'mail_imap_mailbox'    => trim($_POST['mail_imap_mailbox'] ?? ''),
            'mail_imap_username'   => trim($_POST['mail_imap_username'] ?? ''),
            'mail_imap_password'   => $_POST['mail_imap_password'] ?? '',
            'mail_max_messages'    => (string)max(1, (int)($_POST['mail_max_messages'] ?? 50)),
            'mail_search_criteria' => trim($_POST['mail_search_criteria'] ?? 'UNSEEN'),
            'mail_mark_seen'       => isset($_POST['mail_mark_seen']) ? '1' : '0',
            'mail_db_table'        => trim($_POST['mail_db_table'] ?? 'fetched_emails') ?: 'fetched_emails',
        ];
        foreach ($mailFields as $key => $value) {
            setMagnituConfig($pdo, $key, $value);
        }
        $_SESSION['success'] = 'Mail configuration saved.';
    }
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
    exit;
}

function handleDownloadMailConfig($pdo) {
    $hostParts = explode(':', DB_HOST, 2);
    $cfgHost = $hostParts[0];
    $cfgPort = isset($hostParts[1]) ? (int)$hostParts[1] : 3306;

    $imapMailbox  = getMagnituConfig($pdo, 'mail_imap_mailbox') ?: '{imap.example.com:993/imap/ssl}INBOX';
    $imapUsername = getMagnituConfig($pdo, 'mail_imap_username') ?: '';
    $imapPassword = getMagnituConfig($pdo, 'mail_imap_password') ?: '';
    $maxMessages  = getMagnituConfig($pdo, 'mail_max_messages') ?: '50';
    $searchCrit   = getMagnituConfig($pdo, 'mail_search_criteria') ?: 'UNSEEN';
    $markSeen     = getMagnituConfig($pdo, 'mail_mark_seen') ?? '1';
    $dbTable      = getMagnituConfig($pdo, 'mail_db_table') ?: 'fetched_emails';

    $configFile  = "<?php\n";
    $configFile .= "/**\n";
    $configFile .= " * Mail fetcher configuration — generated by Seismo.\n";
    $configFile .= " * Place this file next to fetch_mail.php.\n";
    $configFile .= " */\n\n";
    $configFile .= "return [\n";
    $configFile .= "    'imap' => [\n";
    $configFile .= "        'mailbox'              => " . var_export($imapMailbox, true) . ",\n";
    $configFile .= "        'username'             => " . var_export($imapUsername, true) . ",\n";
    $configFile .= "        'password'             => " . var_export($imapPassword, true) . ",\n";
    $configFile .= "        'max_messages_per_run' => " . var_export((int)$maxMessages, true) . ",\n";
    $configFile .= "        'search_criteria'      => " . var_export($searchCrit, true) . ",\n";
    $configFile .= "        'mark_seen'            => " . ($markSeen === '1' ? 'true' : 'false') . ",\n";
    $configFile .= "    ],\n";
    $configFile .= "    'db' => [\n";
    $configFile .= "        'host'     => " . var_export($cfgHost, true) . ",\n";
    $configFile .= "        'port'     => " . var_export($cfgPort, true) . ",\n";
    $configFile .= "        'database' => " . var_export(DB_NAME, true) . ",\n";
    $configFile .= "        'username' => " . var_export(DB_USER, true) . ",\n";
    $configFile .= "        'password' => " . var_export(DB_PASS, true) . ",\n";
    $configFile .= "        'charset'  => 'utf8mb4',\n";
    $configFile .= "        'table'    => " . var_export($dbTable, true) . ",\n";
    $configFile .= "    ],\n";
    $configFile .= "    'logging' => [\n";
    $configFile .= "        'target' => 'stdout',\n";
    $configFile .= "        'level'  => 'info',\n";
    $configFile .= "    ],\n";
    $configFile .= "];\n";

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="config.php"');
    header('Content-Length: ' . strlen($configFile));
    echo $configFile;
    exit;
}

function handleDownloadMailScript($pdo) {
    $scriptPath = __DIR__ . '/../fetcher/mail/fetch_mail.php';
    if (!file_exists($scriptPath)) {
        $_SESSION['error'] = 'Mail script not found.';
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
        exit;
    }
    $scriptContent = file_get_contents($scriptPath);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="fetch_mail.php"');
    header('Content-Length: ' . strlen($scriptContent));
    echo $scriptContent;
    exit;
}

function handleApiEmailTags($pdo) {
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT DISTINCT tag FROM sender_tags WHERE tag IS NOT NULL AND tag != '' AND tag != 'unclassified' AND removed_at IS NULL ORDER BY tag");
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($tags);
}

// ---------------------------------------------------------------------------
// Sender management
// ---------------------------------------------------------------------------

function handleUpdateSenderTag($pdo) {
    header('Content-Type: application/json');
    
    $fromEmail = trim($_POST['from_email'] ?? '');
    $tag = trim($_POST['tag'] ?? '');
    
    if (empty($fromEmail)) {
        echo json_encode(['success' => false, 'error' => 'Invalid sender email']);
        return;
    }
    
    $stmt = $pdo->prepare("INSERT INTO sender_tags (from_email, tag, disabled) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE tag = ?");
    $stmt->execute([$fromEmail, $tag, $tag]);
    
    echo json_encode(['success' => true, 'tag' => $tag]);
}

function handleToggleSender($pdo) {
    $fromEmail = trim($_POST['email'] ?? $_GET['email'] ?? '');
    $from = $_POST['from'] ?? $_GET['from'] ?? 'settings';
    
    if (empty($fromEmail)) {
        $_SESSION['error'] = 'Invalid sender email';
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
        return;
    }
    
    $stmt = $pdo->prepare("SELECT disabled FROM sender_tags WHERE from_email = ?");
    $stmt->execute([$fromEmail]);
    $result = $stmt->fetch();
    
    if (!$result) {
        $newStatus = 1;
        $stmt = $pdo->prepare("INSERT INTO sender_tags (from_email, tag, disabled) VALUES (?, 'unclassified', ?)");
        $stmt->execute([$fromEmail, $newStatus]);
    } else {
        $newStatus = $result['disabled'] ? 0 : 1;
        $updateStmt = $pdo->prepare("UPDATE sender_tags SET disabled = ? WHERE from_email = ?");
        $updateStmt->execute([$newStatus, $fromEmail]);
    }
    
    $statusText = $newStatus ? 'disabled' : 'enabled';
    $_SESSION['success'] = 'Sender ' . $statusText . ' successfully';
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
    exit;
}

function handleDeleteSender($pdo) {
    $fromEmail = trim($_POST['email'] ?? $_GET['email'] ?? '');
    $from = $_POST['from'] ?? $_GET['from'] ?? 'settings';
    
    if (empty($fromEmail)) {
        $_SESSION['error'] = 'Invalid sender email';
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE sender_tags SET removed_at = NOW(), tag = 'unclassified' WHERE from_email = ?");
    $stmt->execute([$fromEmail]);
    
    $_SESSION['success'] = "Sender removed from Seismo.\nFuture emails from this address will be tagged as \"unsortiert\" until you reassign them.\nTo stop receiving these emails, you need to manually unsubscribe from the sender's press releases.";
    header('Location: ' . getBasePath() . '/index.php?action=settings&tab=script');
    exit;
}

function handleDeleteEmail($pdo) {
    $emailId = (int)($_GET['id'] ?? 0);
    $confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
    
    if (!$emailId) {
        $_SESSION['error'] = 'Invalid email ID';
        header('Location: ?action=mail');
        return;
    }
    
    if (!$confirm) {
        $_SESSION['error'] = 'Deletion requires confirmation';
        header('Location: ?action=mail');
        return;
    }
    
    try {
        $tableName = getEmailTableName($pdo);
        
        if (!$tableName) {
            $_SESSION['error'] = 'Email table not found';
            header('Location: ?action=mail');
            return;
        }
        
        $checkStmt = $pdo->prepare("SELECT id FROM `$tableName` WHERE id = ?");
        $checkStmt->execute([$emailId]);
        if (!$checkStmt->fetch()) {
            $_SESSION['error'] = 'Email not found';
            header('Location: ?action=mail');
            return;
        }
        
        $deleteStmt = $pdo->prepare("DELETE FROM `$tableName` WHERE id = ?");
        $deleteStmt->execute([$emailId]);
        
        $_SESSION['success'] = 'Email deleted successfully';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error deleting email: ' . $e->getMessage();
    }
    
    header('Location: ?action=mail');
    exit;
}

function handleRenameEmailTag($pdo) {
    header('Content-Type: application/json');
    
    $oldTag = trim($_POST['old_tag'] ?? '');
    $newTag = trim($_POST['new_tag'] ?? '');
    
    if (empty($oldTag) || empty($newTag)) {
        echo json_encode(['success' => false, 'error' => 'Both old and new tag names are required']);
        return;
    }
    
    if ($oldTag === $newTag) {
        echo json_encode(['success' => false, 'error' => 'New tag name must be different from old tag name']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE sender_tags SET tag = ? WHERE tag = ?");
    $stmt->execute([$newTag, $oldTag]);
    
    $affectedRows = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'affected' => $affectedRows]);
}

// ---------------------------------------------------------------------------
// Email refresh
// ---------------------------------------------------------------------------

function refreshEmails($pdo) {
    $isCli = (PHP_SAPI === 'cli');
    try {
        $tableName = getEmailTableName($pdo);
        
        if (!$tableName) {
            if (!$isCli) $_SESSION['error'] = "No emails table found.";
            return;
        }
        
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
            $countResult = $countStmt->fetch();
            $emailCount = $countResult['count'] ?? 0;
            
            $descStmt = $pdo->query("DESCRIBE `$tableName`");
            $columns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!$isCli) {
                $_SESSION['email_refresh_count'] = $emailCount;
                $_SESSION['email_table_name'] = $tableName;
                $_SESSION['email_table_columns'] = $columns;
                
                if ($emailCount > 0) {
                    $_SESSION['success'] = "Emails refreshed successfully. Found $emailCount email(s) in table '$tableName'.";
                } else {
                    $_SESSION['success'] = "Emails refreshed. Table '$tableName' exists but contains 0 emails.";
                }
            }
        } catch (PDOException $e) {
            if (!$isCli) $_SESSION['error'] = "Error querying table '$tableName': " . $e->getMessage();
            else throw $e;
        }
    } catch (PDOException $e) {
        if (!$isCli) $_SESSION['error'] = 'Error refreshing emails: ' . $e->getMessage();
        else throw $e;
    }
}

// ---------------------------------------------------------------------------
// Data retrieval (used by dashboard and search)
// ---------------------------------------------------------------------------

function getEmailsForIndex($pdo, $limit = 30, $selectedEmailTags = []) {
    $emails = [];
    
    try {
        $disabledStmt = $pdo->query("SELECT from_email FROM sender_tags WHERE disabled = 1 OR removed_at IS NOT NULL");
        $disabledEmails = $disabledStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $taggedEmails = [];
        if (!empty($selectedEmailTags)) {
            $tagPlaceholders = implode(',', array_fill(0, count($selectedEmailTags), '?'));
            $tagStmt = $pdo->prepare("SELECT from_email FROM sender_tags WHERE tag IN ($tagPlaceholders) AND removed_at IS NULL");
            $tagStmt->execute($selectedEmailTags);
            $taggedEmails = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $tableName = getEmailTableName($pdo);
        
        if ($tableName) {
            $descStmt = $pdo->query("DESCRIBE `$tableName`");
            $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $isCronjobTable = in_array('from_addr', $tableColumns) && 
                             (in_array('body_text', $tableColumns) || in_array('body_html', $tableColumns));
            
            if ($isCronjobTable) {
                $selectClause = "
                    id,
                    subject,
                    from_addr as from_email,
                    from_addr as from_name,
                    date_utc as date_received,
                    date_utc as date_sent,
                    body_text as text_body,
                    body_html as html_body,
                    created_at
                ";
                $orderBy = "date_utc DESC";
            } else {
                $selectColumns = [];
                $columnMap = [
                    'id' => 'id',
                    'subject' => 'subject',
                    'from_email' => 'from_email',
                    'from_name' => 'from_name',
                    'created_at' => 'created_at',
                    'date_received' => 'date_received',
                    'date_sent' => 'date_sent',
                    'text_body' => 'text_body',
                    'html_body' => 'html_body'
                ];
                
                foreach ($columnMap as $expected => $actual) {
                    if (in_array($actual, $tableColumns)) {
                        $selectColumns[] = "`$actual` as `$expected`";
                    }
                }
                
                if (empty($selectColumns)) {
                    $selectClause = '*';
                } else {
                    $selectClause = implode(', ', $selectColumns);
                }
                
                $orderBy = 'id DESC';
                foreach (['date_received', 'date_utc', 'date_sent', 'created_at', 'id'] as $orderCol) {
                    if (in_array($orderCol, $tableColumns)) {
                        $orderBy = "`$orderCol` DESC";
                        break;
                    }
                }
            }
            
            $whereClause = "1=1";
            $params = [];
            
            if (!empty($disabledEmails)) {
                $placeholders = implode(',', array_fill(0, count($disabledEmails), '?'));
                if ($isCronjobTable) {
                    $whereClause = "from_addr NOT IN ($placeholders)";
                } else {
                    $whereClause = "from_email NOT IN ($placeholders)";
                }
                $params = $disabledEmails;
            }
            
            if (!empty($selectedEmailTags) && !empty($taggedEmails)) {
                $tagPlaceholders = implode(',', array_fill(0, count($taggedEmails), '?'));
                if ($isCronjobTable) {
                    $whereClause .= " AND from_addr IN ($tagPlaceholders)";
                } else {
                    $whereClause .= " AND from_email IN ($tagPlaceholders)";
                }
                $params = array_merge($params, $taggedEmails);
            } elseif (!empty($selectedEmailTags) && empty($taggedEmails)) {
                return [];
            }
            
            $sql = "SELECT $selectClause FROM `$tableName` WHERE $whereClause ORDER BY $orderBy LIMIT $limit";
            if (!empty($params)) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->query($sql);
            }
            $emails = $stmt->fetchAll();
            
            foreach ($emails as &$email) {
                if (isset($email['from_email']) && isset($email['from_name']) && 
                    $email['from_email'] === $email['from_name'] && 
                    !empty($email['from_email'])) {
                    $fromAddr = $email['from_email'];
                    if (preg_match('/^"([^"]+)"\s*<(.+)>$/', $fromAddr, $matches)) {
                        $email['from_name'] = $matches[1];
                        $email['from_email'] = $matches[2];
                    } elseif (preg_match('/^(.+)\s*<(.+)>$/', $fromAddr, $matches)) {
                        $email['from_name'] = trim($matches[1]);
                        $email['from_email'] = $matches[2];
                    } elseif (preg_match('/^(.+@.+)$/', $fromAddr)) {
                        $email['from_email'] = $fromAddr;
                        $email['from_name'] = '';
                    }
                }
            }
            unset($email);
            attachSenderTags($pdo, $emails);
        }
    } catch (PDOException $e) {
        // Error getting emails, return empty array
    }
    
    return $emails;
}

function searchEmails($pdo, $query, $limit = 100, $selectedEmailTags = []) {
    $emails = [];
    $searchTerm = '%' . $query . '%';
    
    try {
        $disabledStmt = $pdo->query("SELECT from_email FROM sender_tags WHERE disabled = 1 OR removed_at IS NOT NULL");
        $disabledEmails = $disabledStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $taggedEmails = [];
        if (!empty($selectedEmailTags)) {
            $tagPlaceholders = implode(',', array_fill(0, count($selectedEmailTags), '?'));
            $tagStmt = $pdo->prepare("SELECT from_email FROM sender_tags WHERE tag IN ($tagPlaceholders) AND removed_at IS NULL");
            $tagStmt->execute($selectedEmailTags);
            $taggedEmails = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $tableName = getEmailTableName($pdo);
        
        if ($tableName) {
            $descStmt = $pdo->query("DESCRIBE `$tableName`");
            $tableColumns = $descStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $isCronjobTable = in_array('from_addr', $tableColumns) && 
                             (in_array('body_text', $tableColumns) || in_array('body_html', $tableColumns));
            
            if ($isCronjobTable) {
                $selectClause = "
                    id,
                    subject,
                    from_addr as from_email,
                    from_addr as from_name,
                    date_utc as date_received,
                    date_utc as date_sent,
                    body_text as text_body,
                    body_html as html_body,
                    created_at
                ";
                $whereClause = "(subject LIKE ? OR body_text LIKE ? OR body_html LIKE ? OR from_addr LIKE ?)";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            } else {
                $selectColumns = [];
                $whereColumns = [];
                $columnMap = [
                    'id' => 'id',
                    'subject' => 'subject',
                    'from_email' => 'from_email',
                    'from_name' => 'from_name',
                    'created_at' => 'created_at',
                    'date_received' => 'date_received',
                    'date_sent' => 'date_sent',
                    'text_body' => 'text_body',
                    'html_body' => 'html_body'
                ];
                
                foreach ($columnMap as $expected => $actual) {
                    if (in_array($actual, $tableColumns)) {
                        $selectColumns[] = "`$actual` as `$expected`";
                        if (in_array($actual, ['subject', 'from_email', 'from_name', 'text_body', 'html_body'])) {
                            $whereColumns[] = "`$actual` LIKE ?";
                        }
                    }
                }
                
                if (empty($selectColumns)) {
                    $selectClause = '*';
                    $whereClause = "1=1";
                    $params = [];
                } else {
                    $selectClause = implode(', ', $selectColumns);
                    $whereClause = '(' . implode(' OR ', $whereColumns) . ')';
                    $params = array_fill(0, count($whereColumns), $searchTerm);
                }
            }
            
            $whereParts = [$whereClause];
            $whereParams = $params;
            
            if (!empty($disabledEmails)) {
                $placeholders = implode(',', array_fill(0, count($disabledEmails), '?'));
                if ($isCronjobTable) {
                    $whereParts[] = "from_addr NOT IN ($placeholders)";
                } else {
                    $whereParts[] = "from_email NOT IN ($placeholders)";
                }
                $whereParams = array_merge($whereParams, $disabledEmails);
            }
            
            if (!empty($selectedEmailTags) && !empty($taggedEmails)) {
                $tagPlaceholders = implode(',', array_fill(0, count($taggedEmails), '?'));
                if ($isCronjobTable) {
                    $whereParts[] = "from_addr IN ($tagPlaceholders)";
                } else {
                    $whereParts[] = "from_email IN ($tagPlaceholders)";
                }
                $whereParams = array_merge($whereParams, $taggedEmails);
            } elseif (!empty($selectedEmailTags) && empty($taggedEmails)) {
                return [];
            }
            
            $finalWhereClause = implode(' AND ', $whereParts);
            
            if ($isCronjobTable) {
                $searchOrderBy = "date_utc DESC, id DESC";
            } else {
                $searchOrderBy = 'id DESC';
                foreach (['date_received', 'date_utc', 'date_sent', 'created_at', 'id'] as $sOrdCol) {
                    if (in_array($sOrdCol, $tableColumns)) {
                        $searchOrderBy = "`$sOrdCol` DESC";
                        break;
                    }
                }
            }
            
            $stmt = $pdo->prepare("
                SELECT $selectClause
                FROM `$tableName`
                WHERE $finalWhereClause
                ORDER BY $searchOrderBy
                LIMIT $limit
            ");
            $stmt->execute($whereParams);
            $emails = $stmt->fetchAll();
            
            foreach ($emails as &$email) {
                if (isset($email['from_email']) && isset($email['from_name']) && 
                    $email['from_email'] === $email['from_name'] && 
                    !empty($email['from_email'])) {
                    $fromAddr = $email['from_email'];
                    if (preg_match('/^"([^"]+)"\s*<(.+)>$/', $fromAddr, $matches)) {
                        $email['from_name'] = $matches[1];
                        $email['from_email'] = $matches[2];
                    } elseif (preg_match('/^(.+)\s*<(.+)>$/', $fromAddr, $matches)) {
                        $email['from_name'] = trim($matches[1]);
                        $email['from_email'] = $matches[2];
                    } elseif (preg_match('/^(.+@.+)$/', $fromAddr)) {
                        $email['from_email'] = $fromAddr;
                        $email['from_name'] = '';
                    }
                }
            }
            unset($email);
            attachSenderTags($pdo, $emails);
        }
    } catch (PDOException $e) {
        // Error searching emails, return empty array
    }
    
    return $emails;
}

function attachSenderTags($pdo, &$emails) {
    if (empty($emails)) return;
    try {
        $tagMapStmt = $pdo->query("SELECT from_email, tag FROM sender_tags WHERE removed_at IS NULL AND tag IS NOT NULL AND tag != ''");
        $tagMap = [];
        while ($row = $tagMapStmt->fetch()) {
            $raw = strtolower(trim($row['from_email']));
            $tagMap[$raw] = $row['tag'];
            if (preg_match('/<([^>]+)>/', $raw, $m)) {
                $tagMap[strtolower(trim($m[1]))] = $row['tag'];
            }
        }
        foreach ($emails as &$email) {
            $addr = strtolower(trim($email['from_email'] ?? ''));
            $email['sender_tag'] = $tagMap[$addr] ?? null;
        }
        unset($email);
    } catch (PDOException $e) {
        // sender_tags table might not exist
    }
}
