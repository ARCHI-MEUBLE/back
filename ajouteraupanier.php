<?php
$produitFile = 'produit.json';
$devisFile = 'devis.json';

// Vérifier si les fichiers existent
if (!file_exists($produitFile)) {
    die("Erreur : le fichier produit.json est introuvable.");
}
if (!file_exists($devisFile)) {
    die("Erreur : le fichier devis.json est introuvable.");
}

// Charger le contenu des fichiers JSON
$produitData = json_decode(file_get_contents($produitFile), true);
if ($produitData === null) {
    die("Erreur : impossible de lire le contenu de produit.json.");
}

$devisData = json_decode(file_get_contents($devisFile), true);
if ($devisData === null) {
    die("Erreur : impossible de lire le contenu de devis.json.");
}

// Vérifier que "Produits" est un tableau dans devis.json
if (!isset($devisData['Produits']) || !is_array($devisData['Produits'])) {
    $devisData['Produits'] = [];
}

// Ajouter produitData en tant qu'élément
array_push($devisData['Produits'], $produitData);

// Enregistrer les modifications dans devis.json
if (file_put_contents($devisFile, json_encode($devisData, JSON_PRETTY_PRINT)) === false) {
    die("Erreur : impossible de mettre à jour devis.json.");
}

// Redirection vers display.php
header("Location: 02.php");
exit(); // Assurez-vous que le script s'arrête après la redirection.
?>
