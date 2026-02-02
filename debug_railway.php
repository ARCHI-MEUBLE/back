<?php
/**
 * Script de diagnostic pour Railway
 * Ce script aide à identifier pourquoi l'application pourrait échouer en production.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== ARCHIMEUBLE DIAGNOSTIC SYSTEM ===\n\n";

// 1. Informations PHP et Système
echo "--- Système ---\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Interface: " . php_sapi_name() . "\n";
echo "OS: " . PHP_OS . "\n";
echo "User: " . get_current_user() . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n\n";

// 2. Vérification du fichier .env
echo "--- Configuration (.env) ---\n";
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    echo "✓ Fichier .env trouvé\n";
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "Nombre de lignes: " . count($lines) . "\n";
} else {
    echo "✗ Fichier .env INTROUVABLE à: $envPath\n";
}

// 3. Variables d'Environnement Critiques
echo "\n--- Variables d'Environnement (Cibles) ---\n";
$criticalVars = [
    'ADMIN_EMAIL',
    'SMTP_HOST',
    'DB_PATH',
    'CALENDLY_PHONE_URL',
    'NEXT_PUBLIC_API_URL',
    'RAILWAY_STATIC_URL'
];

foreach ($criticalVars as $var) {
    $val = getenv($var);
    echo "$var: " . ($val ? "DÉFINIE" : "NON DÉFINIE") . "\n";
}

// 4. Base de données
echo "\n--- Base de Données (SQLite) ---\n";
$dbPath = getenv('DB_PATH') ?: __DIR__ . '/database/archimeuble.db';
echo "Chemin DB: $dbPath\n";

if (file_exists($dbPath)) {
    echo "✓ Fichier DB trouvé\n";
    try {
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✓ Connexion PDO réussie\n";
        
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables trouvées: " . implode(', ', $tables) . "\n";
    } catch (Exception $e) {
        echo "✗ Erreur de connexion: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ Fichier DB INTROUVABLE\n";
    // Vérifier si le dossier parent est scriptable
    $dir = dirname($dbPath);
    if (is_dir($dir)) {
        echo "Dossier parent trouvé: $dir\n";
        echo "Dossier accessible en écriture: " . (is_writable($dir) ? "OUI" : "NON") . "\n";
    } else {
        echo "✗ Dossier parent INTROUVABLE: $dir\n";
    }
}

// 5. Python
echo "\n--- Python ---\n";
$pythonPath = getenv('PYTHON_PATH') ?: 'python3';
echo "Chemin Python configuré: $pythonPath\n";
exec("$pythonPath --version 2>&1", $output, $returnCode);
if ($returnCode === 0) {
    echo "✓ Python détecté: " . implode('', $output) . "\n";
} else {
    echo "✗ Python INTROUVABLE ou erreur: " . implode('', $output) . "\n";
}

// 6. Permissions de fichiers (Génération 3D)
echo "\n--- Permissions (Uploads/Models) ---\n";
$folders = ['uploads', 'backend/python/pieces', 'database'];
foreach ($folders as $f) {
    $p = __DIR__ . '/' . $f;
    if (is_dir($p)) {
        echo "$f: " . (is_writable($p) ? "ÉCRITURE OK" : "LECTURE SEULE") . " ($p)\n";
    } else {
        echo "$f: INTROUVABLE ($p)\n";
    }
}

echo "\n--- Fin du diagnostic ---\n";
