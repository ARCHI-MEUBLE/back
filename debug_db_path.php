<?php
// Script de diagnostic pour voir le chemin DB utilisé
echo "=== DIAGNOSTIC DATABASE PATH ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

echo "Test 1: Le fichier DB existe-t-il ?\n";
echo "  /data/database/archimeuble.db existe : " . (file_exists('/data/database/archimeuble.db') ? 'OUI' : 'NON') . "\n";
echo "  /app/database/archimeuble.db existe : " . (file_exists('/app/database/archimeuble.db') ? 'OUI' : 'NON') . "\n\n";

echo "Test 2: Permissions sur /data/database/\n";
system("ls -lh /data/database/ 2>&1");
echo "\n\n";

echo "Test 3: Taille du fichier DB\n";
if (file_exists('/data/database/archimeuble.db')) {
    echo "  Taille: " . filesize('/data/database/archimeuble.db') . " bytes\n";
    echo "  Lisible: " . (is_readable('/data/database/archimeuble.db') ? 'OUI' : 'NON') . "\n";
    echo "  Accessible en écriture: " . (is_writable('/data/database/archimeuble.db') ? 'OUI' : 'NON') . "\n";
} else {
    echo "  Fichier introuvable!\n";
}
echo "\n";

echo "Test 4: Variable d'environnement DB_PATH\n";
echo "  DB_PATH = '" . (getenv('DB_PATH') ?: 'NON DEFINIE') . "'\n\n";

echo "Test 5: Test de connexion PDO\n";
try {
    $pdo = new PDO('sqlite:/data/database/archimeuble.db');
    echo "  Connexion PDO: OK\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM models");
    echo "  Nombre de modèles: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "  Erreur PDO: " . $e->getMessage() . "\n";
}
