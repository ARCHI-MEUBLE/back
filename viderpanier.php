<?php
$json_file = 'devis.json';

// Vérifier si le fichier existe
if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
    if ($data === null) {
        die("Erreur : impossible de lire le contenu de devis.json.");
    }
    if (isset($data['Produits'])) {
        $data['Produits'] = [];
    }
    file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
} else {
    die("Erreur : le fichier devis.json est introuvable.");
}

// Redirection vers display.php
header("Location: 02.php");
exit(); // Assurez-vous que le script s'arrête après la redirection.
?>
