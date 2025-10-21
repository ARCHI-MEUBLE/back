<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $description = $_POST['description'] ?? '';
    $prixht = $_POST['prixht'] ?? 0;
    $image = $_FILES['image']['name'] ?? '';

    // Calcul automatique du Prix TTC
    $prixht = (float)$prixht;
    $prixttc = $prixht * 1.2;

    // Validation et gestion de l'upload de l'image
    $uploadDir = 'uploads/';
    $uploadFile = $uploadDir . basename($_FILES['image']['name']);

    if (!empty($_FILES['image']['tmp_name'])) {
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
            // Image uploadée avec succès
        } else {
            die("Erreur lors de l'upload de l'image.");
        }
    } else {
        // Si aucune image n'est fournie, utiliser une image par défaut
        $uploadFile = './presta.png';
    }

    // Chargement du fichier produit.json
    $jsonFile = 'produit.json';
    $produitData = [];
    if (file_exists($jsonFile)) {
        $produitData = json_decode(file_get_contents($jsonFile), true);
    }

    // Mise à jour des données
    $produitData['Description'] = $description;
    $produitData['prixht'] = $prixht;
    $produitData['prixttc'] = $prixttc;
    $produitData['image'] = $uploadFile;

    // Enregistrement dans produit.json
    file_put_contents($jsonFile, json_encode($produitData, JSON_PRETTY_PRINT));

    // Redirection vers ajouteraupanier.php
    header('Location: ajouteraupanier.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une Prestation</title>
</head>
<body>
    <h1>Ajouter une Prestation</h1>
    <form action="ajouterpresta.php" method="POST" enctype="multipart/form-data">
        <label for="description">Description :</label><br>
        <input type="text" id="description" name="description" required><br><br>

        <label for="prixht">Prix HT :</label><br>
        <input type="number" id="prixht" name="prixht" step="0.01" required><br><br>

        <label for="image">Image :</label><br>
        <input type="file" id="image" name="image" accept="image/*"><br><br>

        <button type="submit">Ajouter</button>
    </form>
</body>
</html>
