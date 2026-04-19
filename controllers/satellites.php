<?php
/**
 * Satellites controller — mothership-only registry of satellite Seismo
 * instances. Each satellite has its own slug, Magnitu profile, API key, and
 * brand accent. The registry is stored as a JSON array in the `magnitu_config`
 * table under key `satellites_registry` — no schema change required.
 *
 * Exports a `satellite-<slug>.json` bundle ready to feed into the external
 * `seismo-generator` tool (which handles the site archive + deploy scaffolding).
 *
 * Routes: satellite_add, satellite_remove, satellite_rotate_key,
 *         satellite_download_json, satellite_rotate_refresh_key.
 * All are mothership-only; the index.php guard rejects them on satellites.
 */

/** Returns the current list of satellites. */
function getSatellitesRegistry(PDO $pdo): array {
    $raw = getMagnituConfig($pdo, 'satellites_registry');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** Writes the registry back as JSON. */
function saveSatellitesRegistry(PDO $pdo, array $registry): void {
    setMagnituConfig($pdo, 'satellites_registry', json_encode(array_values($registry), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/** 32-byte hex keys — same length as the existing Magnitu API key format. */
function generateSatelliteKey(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Normalises user-entered slug. Lowercase, [a-z0-9-] only, 1–40 chars.
 * Collides with neither file system nor URL path.
 */
function normaliseSatelliteSlug(string $slug): string {
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 40);
}

/** Returns the absolute mothership URL used inside exported satellite.json. */
function detectMothershipUrl(): string {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return $scheme . '://' . $host . $path;
}

/** Mothership DB name (for cross-DB reads from satellite). */
function detectMothershipDbName(PDO $pdo): string {
    try {
        return (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    } catch (PDOException $e) {
        return '';
    }
}

/** Returns the shared remote-refresh key if configured, empty otherwise. */
function getRemoteRefreshKey(): string {
    return defined('SEISMO_REMOTE_REFRESH_KEY') ? (string)SEISMO_REMOTE_REFRESH_KEY : '';
}

/** POST ?action=satellite_add — append a satellite to the registry. */
function handleSatelliteAdd(PDO $pdo): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=settings&tab=satellite');
        exit;
    }

    $slug = normaliseSatelliteSlug((string)($_POST['slug'] ?? ''));
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $profile = normaliseSatelliteSlug((string)($_POST['magnitu_profile'] ?? $slug));
    $accent = trim((string)($_POST['brand_accent'] ?? ''));

    if ($slug === '') {
        $_SESSION['error'] = 'Slug is required (letters, numbers, dashes).';
        header('Location: ?action=settings&tab=satellite');
        exit;
    }
    if ($displayName === '') {
        $displayName = 'Seismo ' . ucfirst($slug);
    }
    if ($accent !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) {
        $_SESSION['error'] = 'Accent colour must be a hex value like #4a90e2.';
        header('Location: ?action=settings&tab=satellite');
        exit;
    }

    $registry = getSatellitesRegistry($pdo);
    foreach ($registry as $sat) {
        if (($sat['slug'] ?? '') === $slug) {
            $_SESSION['error'] = "Satellite '{$slug}' already exists. Use rotate key or remove first.";
            header('Location: ?action=settings&tab=satellite');
            exit;
        }
    }

    $registry[] = [
        'slug' => $slug,
        'display_name' => $displayName,
        'magnitu_profile' => $profile !== '' ? $profile : $slug,
        'brand_accent' => $accent,
        'api_key' => generateSatelliteKey(),
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    saveSatellitesRegistry($pdo, $registry);

    $_SESSION['success'] = "Satellite '{$slug}' added. Download its JSON to feed into seismo-generator.";
    header('Location: ?action=settings&tab=satellite&highlight=' . urlencode($slug));
    exit;
}

/** POST ?action=satellite_remove&slug=… */
function handleSatelliteRemove(PDO $pdo): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=settings&tab=satellite');
        exit;
    }
    $slug = normaliseSatelliteSlug((string)($_POST['slug'] ?? ''));
    $registry = getSatellitesRegistry($pdo);
    $filtered = array_values(array_filter($registry, fn($s) => ($s['slug'] ?? '') !== $slug));
    saveSatellitesRegistry($pdo, $filtered);
    $_SESSION['success'] = "Satellite '{$slug}' removed from registry. The satellite's own database is untouched.";
    header('Location: ?action=settings&tab=satellite');
    exit;
}

/** POST ?action=satellite_rotate_key&slug=… — rotate a single satellite's API key. */
function handleSatelliteRotateKey(PDO $pdo): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=settings&tab=satellite');
        exit;
    }
    $slug = normaliseSatelliteSlug((string)($_POST['slug'] ?? ''));
    $registry = getSatellitesRegistry($pdo);
    $found = false;
    foreach ($registry as &$sat) {
        if (($sat['slug'] ?? '') === $slug) {
            $sat['api_key'] = generateSatelliteKey();
            $sat['rotated_at'] = gmdate('Y-m-d\TH:i:s\Z');
            $found = true;
            break;
        }
    }
    unset($sat);

    if (!$found) {
        $_SESSION['error'] = "Satellite '{$slug}' not found.";
        header('Location: ?action=settings&tab=satellite');
        exit;
    }

    saveSatellitesRegistry($pdo, $registry);
    $_SESSION['success'] = "API key rotated for '{$slug}'. Download new JSON and re-deploy the satellite.";
    header('Location: ?action=settings&tab=satellite&highlight=' . urlencode($slug));
    exit;
}

/** POST ?action=satellite_rotate_refresh_key — rotate the shared SEISMO_REMOTE_REFRESH_KEY advice. */
function handleSatelliteRotateRefreshKey(PDO $pdo): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=settings&tab=satellite');
        exit;
    }
    // We cannot write to config.local.php automatically — instead we stash a
    // suggested new key so the user can paste it into both sides. The
    // mothership's actual value still comes from the PHP constant.
    setMagnituConfig($pdo, 'satellites_suggested_refresh_key', generateSatelliteKey());
    $_SESSION['success'] = 'Generated a new suggested SEISMO_REMOTE_REFRESH_KEY. Paste it into mothership + satellite config.local.php.';
    header('Location: ?action=settings&tab=satellite');
    exit;
}

/** GET ?action=satellite_download_json&slug=… — streams satellite-<slug>.json. */
function handleSatelliteDownloadJson(PDO $pdo): void {
    $slug = normaliseSatelliteSlug((string)($_GET['slug'] ?? ''));
    $registry = getSatellitesRegistry($pdo);
    $sat = null;
    foreach ($registry as $row) {
        if (($row['slug'] ?? '') === $slug) { $sat = $row; break; }
    }
    if (!$sat) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo "Satellite '{$slug}' not found.\n";
        exit;
    }

    $refreshKey = getRemoteRefreshKey();
    if ($refreshKey === '') {
        $suggested = getMagnituConfig($pdo, 'satellites_suggested_refresh_key');
        $refreshKey = $suggested ?: '<SET SEISMO_REMOTE_REFRESH_KEY IN MOTHERSHIP config.local.php>';
    }

    $payload = [
        'schema_version' => 1,
        'slug' => $sat['slug'],
        'display_name' => $sat['display_name'],
        'mothership_url' => detectMothershipUrl(),
        'mothership_db' => detectMothershipDbName($pdo),
        'mothership_remote_refresh_key' => $refreshKey,
        'magnitu' => [
            'api_key' => $sat['api_key'],
            'profile_slug' => $sat['magnitu_profile'] ?? $sat['slug'],
        ],
        'brand' => [
            'accent' => $sat['brand_accent'] ?? '',
            'title' => $sat['display_name'],
        ],
        'filters' => [
            'labels' => ['investigation_lead', 'important'],
        ],
        'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="satellite-' . $slug . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}
