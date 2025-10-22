<?php
$sqlite3Command = 'F:\\ANACONDA\\Library\\bin\\sqlite3.exe';
$dbPath = __DIR__ . '/database/archimeuble.db';
$query = "INSERT INTO models (name, description, prompt, base_price, image_path) VALUES ('Test Melomania', 'Test description ssjsj', 'M1(1700,500,730)EFH2(F,T,F)', NULL, '/images/test.jpg')";

// Supprimer les retours à la ligne
$query = preg_replace('/\s+/', ' ', trim($query));

// Échapper les guillemets
$query = str_replace('"', '""', $query);

// Construire la commande
$command = sprintf(
    '"%s" "%s" ".mode json" ".once stdout" "%s"',
    $sqlite3Command,
    $dbPath,
    $query
);

echo "Command:\n$command\n\n";
echo "Executing...\n";

$output = shell_exec($command . ' 2>&1');

echo "Output: '" . $output . "'\n";
echo "Output length: " . strlen($output) . "\n";
