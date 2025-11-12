<?php
/**
 * ENDPOINT CACHÉ DE GESTION DES BACKUPS
 *
 * ⚠️ ATTENTION : Cet endpoint est CONFIDENTIEL
 * - Ne JAMAIS mentionner dans l'interface admin
 * - Ne JAMAIS créer de lien vers cet endpoint
 * - Authentification par clé API secrète uniquement
 * - Rate limiting strict
 *
 * Usage:
 * GET  /api/system/db-maintenance?key=SECRET               → Liste backups
 * GET  /api/system/db-maintenance/download/:file?key=SECRET → Télécharge backup
 * POST /api/system/db-maintenance/restore?key=SECRET       → Restaure backup
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

// Configuration
$BACKUP_DIR = '/data/backups';
$DB_PATH = getenv('DB_PATH') ?: '/data/archimeuble_test.db';
$BACKUP_API_KEY = getenv('BACKUP_API_KEY');
$LOG_FILE = '/data/backup-access.log';

// Rate limiting (10 requêtes/heure par IP)
$RATE_LIMIT_FILE = '/data/backup-rate-limit.json';
$MAX_REQUESTS_PER_HOUR = 10;

/**
 * Log des accès (succès ET échecs)
 */
function logAccess($action, $success, $ip, $details = '') {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $log = "[$timestamp] $status | IP: $ip | Action: $action | $details\n";
    file_put_contents($LOG_FILE, $log, FILE_APPEND);
}

/**
 * Vérifier le rate limiting
 */
function checkRateLimit($ip) {
    global $RATE_LIMIT_FILE, $MAX_REQUESTS_PER_HOUR;

    $rateLimits = [];
    if (file_exists($RATE_LIMIT_FILE)) {
        $rateLimits = json_decode(file_get_contents($RATE_LIMIT_FILE), true) ?: [];
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
    if (isset($rateLimits[$ip]) && count($rateLimits[$ip]) >= $MAX_REQUESTS_PER_HOUR) {
        return false;
    }

    // Ajouter la nouvelle requête
    if (!isset($rateLimits[$ip])) {
        $rateLimits[$ip] = [];
    }
    $rateLimits[$ip][] = $now;

    // Sauvegarder
    file_put_contents($RATE_LIMIT_FILE, json_encode($rateLimits));

    return true;
}

/**
 * Vérifier l'authentification par clé API
 */
function checkAuth() {
    global $BACKUP_API_KEY;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Vérifier que la clé API est configurée
    if (empty($BACKUP_API_KEY)) {
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

    if (empty($providedKey) || $providedKey !== $BACKUP_API_KEY) {
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
    global $BACKUP_DIR;

    if (!is_dir($BACKUP_DIR)) {
        mkdir($BACKUP_DIR, 0755, true);
    }

    $files = glob($BACKUP_DIR . '/database-backup-*.db');
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
 * Télécharger un backup
 */
function downloadBackup($filename) {
    global $BACKUP_DIR;

    // Sécurité: vérifier que le nom de fichier est valide
    if (!preg_match('/^database-backup-[\d_-]+\.db$/', $filename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename']);
        exit;
    }

    $filepath = $BACKUP_DIR . '/' . $filename;

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
 * Restaurer un backup
 */
function restoreBackup() {
    global $BACKUP_DIR, $DB_PATH;

    $input = json_decode(file_get_contents('php://input'), true);
    $filename = $input['filename'] ?? '';

    // Sécurité
    if (!preg_match('/^database-backup-[\d_-]+\.db$/', $filename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename']);
        exit;
    }

    $backupPath = $BACKUP_DIR . '/' . $filename;

    if (!file_exists($backupPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup not found']);
        exit;
    }

    // Créer un backup de sécurité avant restauration
    $emergencyBackup = $DB_PATH . '.before-restore-' . date('Y-m-d_H-i-s');
    copy($DB_PATH, $emergencyBackup);

    // Restaurer
    if (copy($backupPath, $DB_PATH)) {
        return [
            'success' => true,
            'message' => 'Database restored successfully',
            'restored_from' => $filename,
            'emergency_backup' => basename($emergencyBackup)
        ];
    } else {
        // Restaurer le backup d'urgence si échec
        copy($emergencyBackup, $DB_PATH);
        unlink($emergencyBackup);

        http_response_code(500);
        echo json_encode(['error' => 'Restore failed']);
        exit;
    }
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
