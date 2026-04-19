<?php
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');

session_start();

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'controllers/lex_jus.php';
require_once 'controllers/scraper.php';
require_once 'controllers/email_subscriptions.php';
require_once 'controllers/mail.php';
require_once 'controllers/rss.php';
require_once 'controllers/dashboard.php';
require_once 'controllers/magnitu.php';
require_once 'controllers/settings.php';
require_once 'controllers/calendar.php';
require_once 'controllers/satellites.php';

// Initialize database tables
initDatabase();

$action = $_GET['action'] ?? 'index';
$pdo = getDbConnection();

// ── Satellite mode action guard ───────────────────────────────────
// Satellites are read-only consumers of mothership entries; they have no
// scrapers, no admin pages, no fetchers. Any action not in this explicit
// allow-list is 404ed. Keep this list minimal — when adding satellite-visible
// features, add their action here.
if (isSatellite()) {
    $satelliteAllowedActions = [
        'index', 'magnitu', 'settings', 'about', 'styleguide',
        'ai_view', 'ai_view_unified',
        'toggle_favourite',
        'refresh_all', 'refresh_all_remote',
        'api_items', 'api_tags', 'api_all_tags', 'api_feeds',
        'api_substack_tags', 'api_email_tags', 'api_stats',
        'magnitu_entries', 'magnitu_scores', 'magnitu_recipe',
        'magnitu_status', 'magnitu_labels',
        'save_magnitu_config', 'regenerate_magnitu_key', 'clear_magnitu_scores',
    ];
    if (!in_array($action, $satelliteAllowedActions, true)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not found (satellite mode): action '" . preg_replace('/[^a-z0-9_]/i', '', (string)$action) . "' is not available on this satellite.\n";
        exit;
    }
}

// Release session lock early for read-only pages (prevents blocking concurrent requests).
$readOnlyActions = ['index', 'feeds', 'view_feed', 'lex', 'jus', 'mail', 'mail_subscriptions', 'substack', 'magnitu', 'calendar', 'settings', 'about', 'beta', 'styleguide',
                    'api_tags', 'api_substack_tags', 'api_email_tags', 'api_all_tags', 'api_items', 'api_stats',
                    'download_rss_config', 'download_substack_config', 'download_lex_config',
                    'download_calendar_config',
                    'magnitu_entries', 'magnitu_status', 'unsubscribe_email_subscription',
                    'refresh_all_remote'];
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

    case 'toggle_favourite':
        handleToggleFavourite($pdo);
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

    case 'feed_diagnostics':
        $fdQ = array_merge(
            ['action' => 'settings', 'tab' => 'feed_diagnostics'],
            array_intersect_key($_GET, array_flip(['key', 'format']))
        );
        header('Location: ' . getBasePath() . '/index.php?' . http_build_query($fdQ));
        exit;

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

    case 'mail_subscriptions':
        handleEmailSubscriptionsPage($pdo);
        break;

    case 'add_email_subscription':
        handleAddEmailSubscription($pdo);
        break;

    case 'edit_email_subscription':
        handleEditEmailSubscription($pdo);
        break;

    case 'toggle_email_subscription':
        handleToggleEmailSubscription($pdo);
        break;

    case 'delete_email_subscription':
        handleDeleteEmailSubscription($pdo);
        break;

    case 'restore_email_subscription':
        handleRestoreEmailSubscription($pdo);
        break;

    case 'rename_email_subscription_category':
        handleRenameEmailSubscriptionCategory($pdo);
        break;

    case 'rebuild_email_subscriptions':
        handleRebuildEmailSubscriptions($pdo);
        break;

    case 'unsubscribe_email_subscription':
        handleUnsubscribeEmailSubscription($pdo);
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

    case 'download_refresh_config':
        handleDownloadRefreshConfig($pdo);
        break;

    case 'download_refresh_script':
        handleDownloadRefreshScript($pdo);
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

    // ── Leg (parliamentary business, action=calendar) ───────────
    case 'calendar':
        handleCalendarPage($pdo);
        break;

    case 'refresh_calendar':
        handleRefreshCalendar($pdo);
        break;

    case 'save_calendar_config':
        handleSaveCalendarConfig($pdo);
        break;

    case 'download_calendar_config':
        handleDownloadCalendarConfig($pdo);
        break;

    case 'upload_calendar_config':
        handleUploadCalendarConfig($pdo);
        break;

    case 'clear_calendar_events':
        handleClearCalendarEvents($pdo);
        break;

    // ── Magnitu / ML ─────────────────────────────────────────────
    case 'magnitu':
        handleMagnituPage($pdo);
        break;

    case 'ai_view_unified':
        header('Location: ' . getBasePath() . '/index.php?action=ai_view&' . http_build_query(array_diff_key($_GET, ['action' => 1])));
        exit;

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
        header('Location: ' . getBasePath() . '/index.php?action=settings&tab=styleguide');
        exit;

    // ── Global Refresh (cross-cutting) ───────────────────────────
    case 'refresh_all':
        handleRefreshAll($pdo);
        break;

    case 'refresh_all_remote':
        handleRefreshAllRemote($pdo);
        break;

    // ── Satellites registry (mothership-only) ────────────────────
    case 'satellite_add':
        handleSatelliteAdd($pdo);
        break;
    case 'satellite_remove':
        handleSatelliteRemove($pdo);
        break;
    case 'satellite_rotate_key':
        handleSatelliteRotateKey($pdo);
        break;
    case 'satellite_rotate_refresh_key':
        handleSatelliteRotateRefreshKey($pdo);
        break;
    case 'satellite_download_json':
        handleSatelliteDownloadJson($pdo);
        break;

    default:
        header('Location: ?action=index');
        exit;
}
