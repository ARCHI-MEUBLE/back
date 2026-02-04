<?php
/**
 * ENDPOINT CACHÉ DE GESTION DES BACKUPS
 *
 * ATTENTION : Cet endpoint est CONFIDENTIEL
 * - Ne JAMAIS mentionner dans l'interface admin
 * - Ne JAMAIS créer de lien vers cet endpoint
 * - Authentification par clé API secrète uniquement
 * - Rate limiting strict
 *
 * Usage:
 * GET  /api/system/db-maintenance?key=SECRET               → Liste backups
 * GET  /api/system/db-maintenance/download/:file?key=SECRET → Télécharge backup
 * POST /api/system/db-maintenance/restore?key=SECRET       → Restaure backup
 * POST /api/system/db-maintenance/create?key=SECRET        → Crée un backup
 */

// Désactiver l'affichage des erreurs
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Headers CORS basiques (pas besoin du fichier cors.php qui démarre la session)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration - Using $GLOBALS to ensure cross-scope availability
$GLOBALS['BACKUP_DIR'] = '/data/backups';
$GLOBALS['DATABASE_URL'] = getenv('DATABASE_URL');
$GLOBALS['BACKUP_API_KEY'] = getenv('BACKUP_API_KEY');
$GLOBALS['LOG_FILE'] = '/data/backup-access.log';

// Rate limiting (10 requêtes/heure par IP)
$GLOBALS['RATE_LIMIT_FILE'] = '/data/backup-rate-limit.json';
$GLOBALS['MAX_REQUESTS_PER_HOUR'] = 10;

/**
 * Log des accès (succès ET échecs)
 */
function logAccess($action, $success, $ip, $details = '') {
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $log = "[$timestamp] $status | IP: $ip | Action: $action | $details\n";
    file_put_contents($GLOBALS['LOG_FILE'], $log, FILE_APPEND);
}

/**
 * Vérifier le rate limiting
 */
function checkRateLimit($ip) {
    $rateLimits = [];
    if (file_exists($GLOBALS['RATE_LIMIT_FILE'])) {
        $rateLimits = json_decode(file_get_contents($GLOBALS['RATE_LIMIT_FILE']), true) ?: [];
    }

    $now = time();
    $oneHourAgo = $now - 3600;

    // Nettoyer les anciennes entrées
    if (isset($rateLimits[$ip])) {
        $rateLimits[$ip] = array_filter($rateLimits[$ip], function($timestamp) use ($oneHourAgo) {
            return $timestamp > $oneHourAgo;
        });
    }

    // Vérifier la limite
    if (isset($rateLimits[$ip]) && count($rateLimits[$ip]) >= $GLOBALS['MAX_REQUESTS_PER_HOUR']) {
        return false;
    }

    // Ajouter la nouvelle requête
    if (!isset($rateLimits[$ip])) {
        $rateLimits[$ip] = [];
    }
    $rateLimits[$ip][] = $now;

    // Sauvegarder
    file_put_contents($GLOBALS['RATE_LIMIT_FILE'], json_encode($rateLimits));

    return true;
}

/**
 * Vérifier l'authentification par clé API
 */
function checkAuth() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Vérifier que la clé API est configurée
    if (empty($GLOBALS['BACKUP_API_KEY'])) {
        logAccess('AUTH_CHECK', false, $ip, 'BACKUP_API_KEY not configured');
        http_response_code(503);
        echo json_encode(['error' => 'Service temporarily unavailable']);
        exit;
    }

    // Vérifier le rate limiting
    if (!checkRateLimit($ip)) {
        logAccess('RATE_LIMIT', false, $ip, 'Too many requests');
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Try again later.']);
        exit;
    }

    // Vérifier la clé API
    $providedKey = $_GET['key'] ?? '';

    if (empty($providedKey) || $providedKey !== $GLOBALS['BACKUP_API_KEY']) {
        logAccess('AUTH', false, $ip, 'Invalid API key');
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    return $ip;
}

/**
 * Lister les backups disponibles
 */
function listBackups() {
    if (!is_dir($GLOBALS['BACKUP_DIR'])) {
        mkdir($GLOBALS['BACKUP_DIR'], 0755, true);
    }

    $files = glob($GLOBALS['BACKUP_DIR'] . '/database-backup-*.sql');
    $backups = [];

    foreach ($files as $file) {
        $filename = basename($file);
        $size = filesize($file);
        $date = filemtime($file);

        $backups[] = [
            'filename' => $filename,
            'size' => round($size / 1024 / 1024, 2) . ' MB',
            'size_bytes' => $size,
            'date' => date('Y-m-d H:i:s', $date),
            'timestamp' => $date
        ];
    }

    // Trier par date décroissante
    usort($backups, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    return $backups;
}

/**
 * Créer un nouveau backup via pg_dump
 */
function createBackup() {
    $backupDate = date('Y-m-d_H-i-s');
    $backupFile = $GLOBALS['BACKUP_DIR'] . '/database-backup-' . $backupDate . '.sql';

    if (!is_dir($GLOBALS['BACKUP_DIR'])) {
        mkdir($GLOBALS['BACKUP_DIR'], 0755, true);
    }

    $databaseUrl = $GLOBALS['DATABASE_URL'];
    $cmd = "pg_dump " . escapeshellarg($databaseUrl) . " > " . escapeshellarg($backupFile) . " 2>&1";
    $output = shell_exec($cmd);

    if (file_exists($backupFile) && filesize($backupFile) > 0) {
        return [
            'success' => true,
            'message' => 'Backup created successfully',
            'filename' => basename($backupFile),
            'size' => round(filesize($backupFile) / 1024 / 1024, 2) . ' MB'
        ];
    } else {
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        http_response_code(500);
        echo json_encode(['error' => 'Backup failed', 'details' => $output]);
        exit;
    }
}

/**
 * Télécharger un backup
 */
function downloadBackup($filename) {
    // Sécurité: vérifier que le nom de fichier est valide
    if (!preg_match('/^database-backup-[\d_-]+\.sql$/', $filename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename']);
        exit;
    }

    $filepath = $GLOBALS['BACKUP_DIR'] . '/' . $filename;

    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup not found']);
        exit;
    }

    // Envoyer le fichier
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

/**
 * Restaurer un backup via psql
 */
function restoreBackup() {
    $input = json_decode(file_get_contents('php://input'), true);
    $filename = $input['filename'] ?? '';

    // Sécurité
    if (!preg_match('/^database-backup-[\d_-]+\.sql$/', $filename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename']);
        exit;
    }

    $backupPath = $GLOBALS['BACKUP_DIR'] . '/' . $filename;

    if (!file_exists($backupPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup not found']);
        exit;
    }

    // Créer un backup de sécurité avant restauration
    $emergencyFile = $GLOBALS['BACKUP_DIR'] . '/database-backup-before-restore-' . date('Y-m-d_H-i-s') . '.sql';
    $databaseUrl = $GLOBALS['DATABASE_URL'];

    $dumpCmd = "pg_dump " . escapeshellarg($databaseUrl) . " > " . escapeshellarg($emergencyFile) . " 2>&1";
    shell_exec($dumpCmd);

    // Restaurer
    $restoreCmd = "psql " . escapeshellarg($databaseUrl) . " < " . escapeshellarg($backupPath) . " 2>&1";
    $output = shell_exec($restoreCmd);

    return [
        'success' => true,
        'message' => 'Database restored successfully',
        'restored_from' => $filename,
        'emergency_backup' => basename($emergencyFile)
    ];
}

// === POINT D'ENTRÉE PRINCIPAL ===

try {
    // Authentification
    $ip = checkAuth();

    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['REQUEST_URI'];

    // Liste des backups
    if ($method === 'GET' && !str_contains($path, '/download/')) {
        logAccess('LIST_BACKUPS', true, $ip);
        $backups = listBackups();
        echo json_encode([
            'success' => true,
            'count' => count($backups),
            'backups' => $backups
        ]);
        exit;
    }

    // Télécharger un backup
    if ($method === 'GET' && preg_match('#/download/([^?]+)#', $path, $matches)) {
        $filename = $matches[1];
        logAccess('DOWNLOAD_BACKUP', true, $ip, "File: $filename");
        downloadBackup($filename);
        exit;
    }

    // Créer un nouveau backup
    if ($method === 'POST' && str_contains($path, '/create')) {
        logAccess('CREATE_BACKUP', true, $ip);
        $result = createBackup();
        echo json_encode($result);
        exit;
    }

    // Restaurer un backup
    if ($method === 'POST') {
        logAccess('RESTORE_BACKUP', true, $ip);
        $result = restoreBackup();
        echo json_encode($result);
        exit;
    }

    // Route inconnue
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);

} catch (Exception $e) {
    error_log("Backup API error: " . $e->getMessage());
    logAccess('ERROR', false, $ip ?? 'unknown', $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
