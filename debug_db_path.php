<?php
// Script de diagnostic pour voir le chemin DB utilisé
echo "=== DIAGNOSTIC DATABASE PATH ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Contenu du fichier Database.php ligne 25:\n";
system("head -n 30 /app/backend/core/Database.php | tail -n 10");
echo "\n\nFichiers dans /data:\n";
system("ls -la /data/ 2>&1");
echo "\n\nFichiers dans /data/database:\n";
system("ls -la /data/database/ 2>&1");
echo "\n\nFichiers dans /app/database:\n";
system("ls -la /app/database/ 2>&1");
