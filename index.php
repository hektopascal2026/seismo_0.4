<?php
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');

session_start();

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'controllers/magnitu.php';
require_once 'controllers/lex_jus.php';
require_once 'controllers/scraper.php';
require_once 'controllers/mail.php';
require_once 'controllers/rss.php';
require_once 'controllers/dashboard.php';
require_once 'controllers/settings.php';

use SimplePie\SimplePie;

// Initialize database tables
initDatabase();

$action = $_GET['action'] ?? 'index';
$pdo = getDbConnection();

// Release session lock early for read-only pages (prevents blocking concurrent requests).
$readOnlyActions = ['index', 'feeds', 'view_feed', 'lex', 'jus', 'mail', 'substack', 'magnitu', 'settings', 'about', 'beta', 'styleguide',
                    'api_tags', 'api_substack_tags', 'api_email_tags', 'api_all_tags', 'api_items', 'api_stats',
                    'download_rss_config', 'download_substack_config', 'download_lex_config',
                    'magnitu_entries', 'magnitu_status'];
if (in_array($action, $readOnlyActions)) {
    $flashSuccess = $_SESSION['success'] ?? null;
    $flashError   = $_SESSION['error']   ?? null;
    unset($_SESSION['success'], $_SESSION['error']);
    session_write_close();
    if ($flashSuccess !== null) $_SESSION['success'] = $flashSuccess;
    if ($flashError   !== null) $_SESSION['error']   = $flashError;
}

switch ($action) {
    // ── Dashboard ────────────────────────────────────────────────
    case 'index':
        handleDashboard($pdo);
        break;

    // ── RSS & Substack ───────────────────────────────────────────
    case 'feeds':
        handleFeedsPage($pdo);
        break;

    case 'substack':
        handleSubstackPage($pdo);
        break;

    case 'add_feed':
        handleAddFeed($pdo);
        break;

    case 'add_substack':
        handleAddSubstack($pdo);
        break;

    case 'delete_feed':
        handleDeleteFeed($pdo);
        break;

    case 'toggle_feed':
        handleToggleFeed($pdo);
        break;

    case 'view_feed':
        handleViewFeed($pdo);
        break;

    case 'refresh_feed':
        handleRefreshFeed($pdo);
        break;

    case 'refresh_all_feeds':
        handleRefreshAllFeeds($pdo);
        break;

    case 'refresh_all_substacks':
        handleRefreshAllSubstacks($pdo);
        break;

    case 'update_feed_tag':
        handleUpdateFeedTag($pdo);
        break;

    case 'rename_tag':
        handleRenameTag($pdo);
        break;

    case 'rename_substack_tag':
        handleRenameSubstackTag($pdo);
        break;

    case 'download_rss_config':
        handleDownloadRssConfig($pdo);
        break;

    case 'upload_rss_config':
        handleUploadRssConfig($pdo);
        break;

    case 'download_substack_config':
        handleDownloadSubstackConfig($pdo);
        break;

    case 'upload_substack_config':
        handleUploadSubstackConfig($pdo);
        break;

    case 'api_feeds':
        handleApiFeeds($pdo);
        break;

    case 'api_items':
        handleApiItems($pdo);
        break;

    case 'api_tags':
        handleApiTags($pdo);
        break;

    case 'api_substack_tags':
        handleApiSubstackTags($pdo);
        break;

    case 'api_all_tags':
        handleApiAllTags($pdo);
        break;

    // ── Mail ─────────────────────────────────────────────────────
    case 'mail':
        handleMailPage($pdo);
        break;

    case 'refresh_emails':
        handleRefreshEmails($pdo);
        break;

    case 'delete_email':
        handleDeleteEmail($pdo);
        break;

    case 'update_sender_tag':
        handleUpdateSenderTag($pdo);
        break;

    case 'toggle_sender':
        handleToggleSender($pdo);
        break;

    case 'delete_sender':
        handleDeleteSender($pdo);
        break;

    case 'rename_email_tag':
        handleRenameEmailTag($pdo);
        break;

    case 'save_mail_config':
        handleSaveMailConfig($pdo);
        break;

    case 'download_mail_config':
        handleDownloadMailConfig($pdo);
        break;

    case 'download_mail_script':
        handleDownloadMailScript($pdo);
        break;

    case 'api_email_tags':
        handleApiEmailTags($pdo);
        break;

    // ── Scraper ──────────────────────────────────────────────────
    case 'scraper':
        handleScraperPage($pdo);
        break;

    case 'add_scraper':
        handleAddScraper($pdo);
        break;

    case 'update_scraper':
        handleUpdateScraper($pdo);
        break;

    case 'toggle_scraper':
        handleToggleScraper($pdo);
        break;

    case 'remove_scraper':
        handleRemoveScraper($pdo);
        break;

    case 'hide_scraper_item':
        handleHideScraperItem($pdo);
        break;

    case 'delete_all_scraper_items':
        handleDeleteAllScraperItems($pdo);
        break;

    case 'rescrape_source':
        handleRescrapeSource($pdo);
        break;

    case 'download_scraper_config':
        handleDownloadScraperConfig($pdo);
        break;

    case 'download_scraper_script':
        handleDownloadScraperScript($pdo);
        break;

    // ── Lex & Jus ────────────────────────────────────────────────
    case 'lex':
        handleLexPage($pdo);
        break;

    case 'jus':
        handleJusPage($pdo);
        break;

    case 'refresh_all_lex':
        handleRefreshAllLex($pdo);
        break;

    case 'refresh_all_jus':
        handleRefreshAllJus($pdo);
        break;

    case 'save_lex_config':
        handleSaveLexConfig($pdo);
        break;

    case 'upload_lex_config':
        handleUploadLexConfig($pdo);
        break;

    case 'download_lex_config':
        handleDownloadLexConfig($pdo);
        break;

    // ── Magnitu / ML ─────────────────────────────────────────────
    case 'magnitu':
        handleMagnituPage($pdo);
        break;

    case 'ai_view_unified':
        handleAiViewUnified($pdo);
        break;

    case 'ai_view':
        handleAiView($pdo);
        break;

    case 'save_magnitu_config':
        handleSaveMagnituConfig($pdo);
        break;

    case 'regenerate_magnitu_key':
        handleRegenerateMagnituKey($pdo);
        break;

    case 'clear_magnitu_scores':
        handleClearMagnituScores($pdo);
        break;

    case 'magnitu_entries':
        handleMagnituEntries($pdo);
        break;

    case 'magnitu_scores':
        handleMagnituScores($pdo);
        break;

    case 'magnitu_recipe':
        handleMagnituRecipe($pdo);
        break;

    case 'magnitu_status':
        handleMagnituStatus($pdo);
        break;

    case 'magnitu_labels':
        handleMagnituLabels($pdo);
        break;

    // ── Settings & Static Pages ──────────────────────────────────
    case 'settings':
        handleSettingsPage($pdo);
        break;

    case 'about':
        handleAboutPage($pdo);
        break;

    case 'beta':
        handleBetaPage();
        break;

    case 'styleguide':
        handleStyleguidePage();
        break;

    // ── Global Refresh (cross-cutting) ───────────────────────────
    case 'refresh_all':
        $lastRefreshAt = getMagnituConfig($pdo, 'last_refresh_at');
        if ($lastRefreshAt && (time() - (int)$lastRefreshAt) < 60) {
            $remaining = 60 - (time() - (int)$lastRefreshAt);
            $_SESSION['error'] = "Please wait {$remaining}s before refreshing again.";
            $currentAction = $_GET['from'] ?? 'index';
            header('Location: ?action=' . $currentAction);
            exit;
        }
        setMagnituConfig($pdo, 'last_refresh_at', (string)time());

        $results = [];

        try {
            refreshAllFeeds($pdo);
            $results[] = 'Feeds refreshed';
        } catch (Exception $e) {
            $results[] = 'Feeds: ' . $e->getMessage();
        }

        try {
            refreshEmails($pdo);
            $results[] = 'Emails refreshed';
        } catch (Exception $e) {
            $results[] = 'Emails: ' . $e->getMessage();
        }

        $lexCfg = getLexConfig();
        if ($lexCfg['eu']['enabled'] ?? true) {
            try { $results[] = "🇪🇺 " . refreshLexItems($pdo) . " lex items"; }
            catch (Exception $e) { $results[] = '🇪🇺 EU: ' . $e->getMessage(); }
        }
        if ($lexCfg['ch']['enabled'] ?? true) {
            try { $results[] = "🇨🇭 " . refreshFedlexItems($pdo) . " lex items"; }
            catch (Exception $e) { $results[] = '🇨🇭 CH: ' . $e->getMessage(); }
        }
        if ($lexCfg['de']['enabled'] ?? true) {
            try { $results[] = "🇩🇪 " . refreshRechtBundItems($pdo) . " lex items"; }
            catch (Exception $e) { $results[] = '🇩🇪 DE: ' . $e->getMessage(); }
        }
        if ($lexCfg['ch_bger']['enabled'] ?? false) {
            try { $results[] = "⚖️ " . refreshJusItems($pdo, 'CH_BGer') . " BGer items"; }
            catch (Exception $e) { $results[] = '⚖️ BGer: ' . $e->getMessage(); }
        }
        if ($lexCfg['ch_bge']['enabled'] ?? false) {
            try { $results[] = "⚖️ " . refreshJusItems($pdo, 'CH_BGE') . " BGE items"; }
            catch (Exception $e) { $results[] = '⚖️ BGE: ' . $e->getMessage(); }
        }
        if ($lexCfg['ch_bvger']['enabled'] ?? false) {
            try { $results[] = "⚖️ " . refreshJusItems($pdo, 'CH_BVGer') . " BVGer items"; }
            catch (Exception $e) { $results[] = '⚖️ BVGer: ' . $e->getMessage(); }
        }

        try {
            $recipeJson = getMagnituConfig($pdo, 'recipe_json');
            if ($recipeJson) {
                $recipeData = json_decode($recipeJson, true);
                if ($recipeData && !empty($recipeData['keywords'])) {
                    magnituRescore($pdo, $recipeData);
                    $results[] = 'Scores updated';
                }
            }
        } catch (Exception $e) {
            $results[] = 'Scoring: ' . $e->getMessage();
        }

        $_SESSION['success'] = implode(' · ', $results);
        $currentAction = $_GET['from'] ?? 'index';
        $redirectUrl = '?action=' . $currentAction;
        if ($currentAction === 'view_feed' && isset($_GET['id'])) {
            $redirectUrl .= '&id=' . (int)$_GET['id'];
        }
        header('Location: ' . $redirectUrl);
        exit;

    default:
        header('Location: ?action=index');
        exit;
}
