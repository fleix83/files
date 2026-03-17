<?php
/**
 * FileDrop PHP API
 *
 * Replaces the Express.js backend. All /files/api/* routes are handled here
 * via .htaccess RewriteRule passing the path as $_GET['route'].
 */

// ---- Configuration ----
define('DATA_DIR', __DIR__ . '/data/sessions');
define('SESSION_TTL_HOURS', 24);
define('MAX_FILES_PER_SESSION', 20);
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB
define('CLEANUP_THROTTLE_SECONDS', 900); // 15 minutes
define('CLEANUP_STAMP_FILE', __DIR__ . '/data/.last_cleanup');

// Public URL for QR codes - adjust if needed
define('PUBLIC_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/files');

define('UUID_REGEX', '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');

// ---- CORS Headers ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Ensure data directory exists ----
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// ---- Throttled cleanup ----
throttledCleanup();

// ---- Routing ----
$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? trim($_GET['route'], '/') : '';

// Parse route segments
$segments = $route !== '' ? explode('/', $route) : [];
$segCount = count($segments);

// Route: POST /api/sessions
if ($method === 'POST' && $segCount === 1 && $segments[0] === 'sessions') {
    handleCreateSession();
}
// Route: GET /api/sessions/:id
elseif ($method === 'GET' && $segCount === 2 && $segments[0] === 'sessions') {
    handleGetSession($segments[1]);
}
// Route: POST /api/sessions/:id/files
elseif ($method === 'POST' && $segCount === 3 && $segments[0] === 'sessions' && $segments[2] === 'files') {
    handleUploadFiles($segments[1]);
}
// Route: GET /api/sessions/:id/files/:filename
elseif ($method === 'GET' && $segCount === 4 && $segments[0] === 'sessions' && $segments[2] === 'files') {
    handleDownloadFile($segments[1], $segments[3]);
}
// Route: DELETE /api/sessions/:id/files/:filename
elseif ($method === 'DELETE' && $segCount === 4 && $segments[0] === 'sessions' && $segments[2] === 'files') {
    handleDeleteFile($segments[1], $segments[3]);
}
// Route: GET /api/sessions/:id/qr
elseif ($method === 'GET' && $segCount === 3 && $segments[0] === 'sessions' && $segments[2] === 'qr') {
    handleQrCode($segments[1]);
}
else {
    jsonResponse(404, ['error' => 'Route nicht gefunden']);
}

// ======================================================================
// Handler Functions
// ======================================================================

function handleCreateSession() {
    try {
        $id = generateUUIDv4();
        $sessionDir = DATA_DIR . '/' . $id;
        mkdir($sessionDir, 0755, true);

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $expiresAt = clone $now;
        $expiresAt->modify('+' . SESSION_TTL_HOURS . ' hours');

        $meta = [
            'id' => $id,
            'createdAt' => $now->format('Y-m-d\TH:i:s.v\Z'),
            'expiresAt' => $expiresAt->format('Y-m-d\TH:i:s.v\Z'),
        ];

        file_put_contents($sessionDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        jsonResponse(201, $meta);
    } catch (Exception $e) {
        error_log('Session creation error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Session konnte nicht erstellt werden']);
    }
}

function handleGetSession($id) {
    if (!validateAndFindSession($id, $sessionDir)) return;

    try {
        $meta = readSessionMeta($id);
        if (isExpired($meta)) {
            jsonResponse(410, ['error' => 'Session abgelaufen']);
            return;
        }
        $files = getSessionFiles($id);
        $response = array_merge($meta, ['files' => $files]);
        jsonResponse(200, $response);
    } catch (Exception $e) {
        error_log('Get session error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Session konnte nicht geladen werden']);
    }
}

function handleUploadFiles($id) {
    if (!validateAndFindSession($id, $sessionDir)) return;

    try {
        $meta = readSessionMeta($id);
        if (isExpired($meta)) {
            jsonResponse(410, ['error' => 'Session abgelaufen']);
            return;
        }

        $existingFiles = getSessionFiles($id);
        if (count($existingFiles) >= MAX_FILES_PER_SESSION) {
            jsonResponse(400, ['error' => 'Maximal ' . MAX_FILES_PER_SESSION . ' Dateien pro Session']);
            return;
        }

        if (!isset($_FILES['files'])) {
            jsonResponse(400, ['error' => 'Keine Dateien hochgeladen']);
            return;
        }

        // Normalize $_FILES['files'] to always be an array of files
        $uploadedFiles = normalizeFilesArray($_FILES['files']);

        foreach ($uploadedFiles as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errMsg = uploadErrorMessage($file['error']);
                jsonResponse(400, ['error' => $errMsg]);
                return;
            }

            if ($file['size'] > MAX_FILE_SIZE) {
                jsonResponse(413, ['error' => 'Datei zu gross (max. 50 MB)']);
                return;
            }

            $safeName = sanitizeFilename($file['name']);
            if ($safeName === '' || $safeName === 'meta.json') {
                jsonResponse(400, ['error' => 'Ungültiger Dateiname']);
                return;
            }

            $dest = $sessionDir . '/' . $safeName;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                jsonResponse(500, ['error' => 'Upload fehlgeschlagen']);
                return;
            }
        }

        $files = getSessionFiles($id);
        jsonResponse(200, ['files' => $files]);
    } catch (Exception $e) {
        error_log('Upload error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Upload fehlgeschlagen']);
    }
}

function handleDownloadFile($id, $filename) {
    if (!validateAndFindSession($id, $sessionDir)) return;

    $safeName = sanitizeFilename(urldecode($filename));
    $filePath = $sessionDir . '/' . $safeName;

    if (!file_exists($filePath) || $safeName === 'meta.json') {
        jsonResponse(404, ['error' => 'Datei nicht gefunden']);
        return;
    }

    $mime = mime_content_type($filePath) ?: 'application/octet-stream';
    $size = filesize($filePath);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($safeName) . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-cache');
    readfile($filePath);
    exit;
}

function handleDeleteFile($id, $filename) {
    if (!validateAndFindSession($id, $sessionDir)) return;

    try {
        $safeName = sanitizeFilename(urldecode($filename));
        $filePath = $sessionDir . '/' . $safeName;

        if (!file_exists($filePath) || $safeName === 'meta.json') {
            jsonResponse(404, ['error' => 'Datei nicht gefunden']);
            return;
        }

        unlink($filePath);
        $files = getSessionFiles($id);
        jsonResponse(200, ['files' => $files]);
    } catch (Exception $e) {
        error_log('Delete error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Datei konnte nicht gelöscht werden']);
    }
}

function handleQrCode($id) {
    if (!validateAndFindSession($id, $sessionDir)) return;

    $url = PUBLIC_URL . '/s/' . $id;

    // Use Google Charts API for QR code generation (no external PHP library needed)
    $qrUrl = 'https://chart.googleapis.com/chart?'
        . http_build_query([
            'cht' => 'qr',
            'chs' => '300x300',
            'chl' => $url,
            'choe' => 'UTF-8',
            'chld' => 'M|2',
        ]);

    // Fetch the QR code image
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'FileDrop/1.0',
        ],
    ]);

    $png = @file_get_contents($qrUrl, false, $context);

    if ($png === false) {
        // Fallback: generate a simple QR code using bundled minimal generator
        $png = generateQrPngFallback($url);
        if ($png === false) {
            jsonResponse(500, ['error' => 'QR-Code konnte nicht generiert werden']);
            return;
        }
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    echo $png;
    exit;
}

// ======================================================================
// Helper Functions
// ======================================================================

function generateUUIDv4(): string {
    $data = random_bytes(16);
    // Set version to 0100 (UUID v4)
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // Set variant to 10xx
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return sprintf(
        '%s-%s-%s-%s-%s',
        bin2hex(substr($data, 0, 4)),
        bin2hex(substr($data, 4, 2)),
        bin2hex(substr($data, 6, 2)),
        bin2hex(substr($data, 8, 2)),
        bin2hex(substr($data, 10, 6))
    );
}

function sanitizeFilename(string $name): string {
    // Get basename to prevent path traversal
    $name = basename($name);
    // Replace unsafe characters, keep common ones including German umlauts
    $name = preg_replace('/[^a-zA-Z0-9._\-()äöüÄÖÜéèàêâ ]/u', '_', $name);
    // Prevent empty names or dot-files
    $name = ltrim($name, '.');
    return $name;
}

function validateSessionId(string $id): bool {
    return (bool) preg_match(UUID_REGEX, $id);
}

function validateAndFindSession(string $id, &$sessionDir): bool {
    if (!validateSessionId($id)) {
        jsonResponse(400, ['error' => 'Ungültige Session-ID']);
        return false;
    }
    $sessionDir = DATA_DIR . '/' . $id;
    if (!is_dir($sessionDir)) {
        jsonResponse(404, ['error' => 'Session nicht gefunden']);
        return false;
    }
    return true;
}

function readSessionMeta(string $id): array {
    $metaPath = DATA_DIR . '/' . $id . '/meta.json';
    $raw = file_get_contents($metaPath);
    if ($raw === false) {
        throw new RuntimeException('Could not read meta.json for session ' . $id);
    }
    return json_decode($raw, true);
}

function isExpired(array $meta): bool {
    $expiresAt = new DateTime($meta['expiresAt']);
    $now = new DateTime('now', new DateTimeZone('UTC'));
    return $expiresAt < $now;
}

function getSessionFiles(string $id): array {
    $sessionDir = DATA_DIR . '/' . $id;
    $files = [];

    $entries = scandir($sessionDir);
    if ($entries === false) return [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'meta.json') continue;
        $filePath = $sessionDir . '/' . $entry;
        if (is_file($filePath)) {
            $files[] = [
                'name' => $entry,
                'size' => filesize($filePath),
                'uploadedAt' => date('Y-m-d\TH:i:s.000\Z', filemtime($filePath)),
            ];
        }
    }

    // Sort by uploadedAt descending (newest first)
    usort($files, function ($a, $b) {
        return strcmp($b['uploadedAt'], $a['uploadedAt']);
    });

    return $files;
}

function jsonResponse(int $statusCode, $data): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeFilesArray(array $files): array {
    // If single file upload, $_FILES['files']['name'] is a string
    if (!is_array($files['name'])) {
        return [$files];
    }

    // Multiple files: restructure from parallel arrays to array of files
    $result = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $result[] = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
    }
    return $result;
}

function uploadErrorMessage(int $error): string {
    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Datei zu gross (max. 50 MB)';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload unvollständig';
        case UPLOAD_ERR_NO_FILE:
            return 'Keine Datei hochgeladen';
        default:
            return 'Upload-Fehler (Code: ' . $error . ')';
    }
}

// ---- Cleanup ----

function throttledCleanup(): void {
    $stampFile = CLEANUP_STAMP_FILE;
    $now = time();

    // Check if we should run cleanup
    if (file_exists($stampFile)) {
        $lastRun = (int) file_get_contents($stampFile);
        if (($now - $lastRun) < CLEANUP_THROTTLE_SECONDS) {
            return; // Too soon, skip cleanup
        }
    }

    // Update timestamp first to prevent concurrent runs
    $dataDir = dirname($stampFile);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    file_put_contents($stampFile, (string) $now);

    // Run cleanup
    cleanExpiredSessions();
}

function cleanExpiredSessions(): void {
    $sessionsDir = DATA_DIR;
    if (!is_dir($sessionsDir)) return;

    $entries = scandir($sessionsDir);
    if ($entries === false) return;

    $now = new DateTime('now', new DateTimeZone('UTC'));

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;

        $sessionDir = $sessionsDir . '/' . $entry;
        if (!is_dir($sessionDir)) continue;

        $metaPath = $sessionDir . '/meta.json';
        if (!file_exists($metaPath)) continue;

        try {
            $raw = file_get_contents($metaPath);
            if ($raw === false) continue;

            $meta = json_decode($raw, true);
            if (!$meta || !isset($meta['expiresAt'])) continue;

            $expiresAt = new DateTime($meta['expiresAt']);
            if ($expiresAt < $now) {
                deleteDirectory($sessionDir);
                error_log('Abgelaufene Session gelöscht: ' . $entry);
            }
        } catch (Exception $e) {
            // Skip sessions without valid meta.json
        }
    }
}

function deleteDirectory(string $dir): void {
    if (!is_dir($dir)) return;

    $entries = scandir($dir);
    if ($entries === false) return;

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

/**
 * Fallback QR code generator using a simple SVG-to-PNG approach.
 * If Google Charts API is unavailable, we redirect to an alternative API.
 */
function generateQrPngFallback(string $url) {
    // Try quickchart.io as fallback
    $fallbackUrl = 'https://quickchart.io/qr?' . http_build_query([
        'text' => $url,
        'size' => 300,
        'margin' => 2,
    ]);

    $context = stream_context_create([
        'http' => ['timeout' => 10, 'user_agent' => 'FileDrop/1.0'],
    ]);

    $png = @file_get_contents($fallbackUrl, false, $context);
    return $png !== false ? $png : false;
}
