<?php
/**
 * Script d'initialisation de la base de données unifiée
 * ArchiMeuble - Backend PHP
 * Date : 2025-10-21
 */

// Chemin vers la base de données unifiée (partagée avec le frontend)
$dbPath = __DIR__ . '/../../database/archimeuble.db';

// Créer le dossier database s'il n'existe pas
$databaseDir = dirname($dbPath);
if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0755, true);
    echo "Dossier database créé : $databaseDir\n";
}

// Chemin vers sqlite3.exe
$sqlite3Path = 'F:\ANACONDA\Library\bin\sqlite3.exe';

if (!file_exists($sqlite3Path)) {
    die("ERREUR : sqlite3.exe introuvable à : $sqlite3Path\n");
}

// Lire le script SQL
$sqlScript = file_get_contents(__DIR__ . '/unified_db.sql');

if ($sqlScript === false) {
    die("ERREUR : Impossible de lire le fichier unified_db.sql\n");
}

// Créer un fichier temporaire pour le script SQL
$tempSqlFile = sys_get_temp_dir() . '/archimeuble_init.sql';
file_put_contents($tempSqlFile, $sqlScript);

// Exécuter le script SQL
$command = sprintf(
    '"%s" "%s" < "%s" 2>&1',
    $sqlite3Path,
    $dbPath,
    $tempSqlFile
);

echo "Exécution de la commande : $command\n";
$output = shell_exec($command);

// Supprimer le fichier temporaire
unlink($tempSqlFile);

if ($output) {
    echo "Sortie SQLite :\n$output\n";
}

// Vérifier que la base de données a été créée
if (file_exists($dbPath)) {
    echo "✓ Base de données créée avec succès : $dbPath\n";
    echo "✓ Taille : " . filesize($dbPath) . " octets\n";
} else {
    echo "✗ ERREUR : La base de données n'a pas été créée\n";
    exit(1);
}

// Vérifier les tables créées
$command = sprintf(
    '"%s" "%s" ".tables" 2>&1',
    $sqlite3Path,
    $dbPath
);

$tables = shell_exec($command);
echo "\nTables créées :\n$tables\n";

// Créer un utilisateur admin par défaut
$adminEmail = 'admin@archimeuble.fr';
$adminPassword = 'admin123';
$adminPasswordHash = password_hash($adminPassword, PASSWORD_BCRYPT);

$insertAdminSql = sprintf(
    "INSERT OR IGNORE INTO admins (email, password_hash) VALUES ('%s', '%s');",
    $adminEmail,
    $adminPasswordHash
);

$tempAdminFile = sys_get_temp_dir() . '/archimeuble_admin.sql';
file_put_contents($tempAdminFile, $insertAdminSql);

$command = sprintf(
    '"%s" "%s" < "%s" 2>&1',
    $sqlite3Path,
    $dbPath,
    $tempAdminFile
);

shell_exec($command);
unlink($tempAdminFile);

echo "\n✓ Administrateur par défaut créé :\n";
echo "   Email : $adminEmail\n";
echo "   Mot de passe : $adminPassword\n";

echo "\n✓ Base de données unifiée initialisée avec succès !\n";
