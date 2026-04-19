<?php
/**
 * views/partials/nav.php
 *
 * Shared top-bar nav drawer. Each view sets $navActive (string, matching one of
 * the action names below) before including this partial so the current tab is
 * highlighted.
 *
 * In satellite mode, scraper / admin items are hidden — only Feed, Magnitu,
 * Settings, About remain. (Beta was retired in 0.4.5; its AI View Generator
 * moved to Settings > LLM.)
 *
 * If SEISMO_BRAND_ACCENT is set, a :root { --seismo-accent: … } CSS variable
 * is emitted once per page; assets/css/style.css uses it as the default
 * active nav-link background so each satellite has a distinct signal.
 */

$navActive = $navActive ?? '';
$navIsSatellite = function_exists('isSatellite') && isSatellite();
$navBrandAccent = function_exists('seismoBrandAccent') ? seismoBrandAccent() : null;

// [action, label, active-color-hex|null, visible-in-satellite]
$navItems = [
    ['index',              'Feed',      null,      true],
    ['magnitu',            'Magnitu',   null,      true],
    ['feeds',              'RSS',       '#add8e6', false],
    ['calendar',           'Calendar',  '#d4edda', false],
    ['lex',                'Lex',       '#f5f562', false],
    ['jus',                'Jus',       '#f5f562', false],
    ['mail',               'Mail',      '#FFDBBB', false],
    ['substack',           'Substack',  '#C5B4D1', false],
    ['scraper',            'Scraper',   '#FFDBBB', false],
    ['settings',           'Settings',  null,      true],
    ['about',              'About',     null,      true],
];

$navHrefFor = static function (string $action): string {
    if (function_exists('seismo_nav_url_for_action') && in_array($action, ['index', 'magnitu'], true)) {
        return seismo_nav_url_for_action($action);
    }
    return '?action=' . urlencode($action);
};
?>
<?php if ($navBrandAccent): ?>
<style>:root { --seismo-accent: <?= htmlspecialchars($navBrandAccent, ENT_QUOTES) ?>; }</style>
<?php endif; ?>
<nav class="nav-drawer" id="navDrawer">
<?php foreach ($navItems as [$navAction, $navLabel, $navColor, $navInSatellite]): ?>
    <?php if ($navIsSatellite && !$navInSatellite) continue; ?>
    <?php
        $navIsActive = ($navActive === $navAction);
        $navClasses  = 'nav-link' . ($navIsActive ? ' active' : '');
        $navStyle    = ($navIsActive && $navColor && !$navIsSatellite)
            ? ' style="background-color: ' . htmlspecialchars($navColor, ENT_QUOTES) . '; color: #000000;"'
            : '';
    ?>
    <a href="<?= htmlspecialchars($navHrefFor($navAction), ENT_QUOTES) ?>" class="<?= $navClasses ?>"<?= $navStyle ?>><?= htmlspecialchars($navLabel) ?></a>
<?php endforeach; ?>
</nav>
