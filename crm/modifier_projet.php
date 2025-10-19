<?php
// Charger le fichier JSON
$file = 'data.json';

if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true); // Convertir le JSON en tableau associatif
} else {
    die('Fichier JSON introuvable.');
}

// Récupérer l'index du client et du projet
if (isset($_GET['client'], $_GET['project']) && 
    isset($data['clients'][$_GET['client']]['projets'][$_GET['project']])) {
    $clientIndex = (int)$_GET['client'];
    $projectIndex = (int)$_GET['project'];
    $projet = $data['clients'][$clientIndex]['projets'][$projectIndex];
} else {
    die('Client ou projet invalide.');
}

// Si le formulaire est soumis, mettre à jour les informations du projet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = $_POST['titre'];
    $description = $_POST['description'];
    $code_meuble = $_POST['code_meuble'];
    $chemin_image = $projet['chemin_image']; // Par défaut, conserver l'image existante

    // Vérifier si une nouvelle image a été uploadée
    if (!empty($_FILES['nouvelle_image']['name'])) {
        $uploadDir = 'uploads/'; // Dossier où stocker les images
        $uploadFile = $uploadDir . basename($_FILES['nouvelle_image']['name']);

        // Vérifier et déplacer le fichier uploadé
        if (move_uploaded_file($_FILES['nouvelle_image']['tmp_name'], $uploadFile)) {
            $chemin_image = $uploadFile; // Mettre à jour le chemin de l'image
        } else {
            die('Erreur lors de l\'upload de l\'image.');
        }
    }

    // Mettre à jour les informations du projet
    $data['clients'][$clientIndex]['projets'][$projectIndex] = [
        'titre' => $titre,
        'description' => $description,
        'code_meuble' => $code_meuble,
        'chemin_image' => $chemin_image,
    ];

    // Sauvegarder les modifications dans le fichier JSON
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    // Rediriger vers la page des projets
    header('Location: projets.php?client=' . $clientIndex);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Projet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            padding: 20px;
        }
        form {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        img {
            max-width: 100%;
            height: auto;
            display: block;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>Modifier Projet</h1>
    <form method="POST" enctype="multipart/form-data">
        <label for="titre">Titre du Projet:</label>
        <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($projet['titre']); ?>" required>

        <label for="description">Description:</label>
        <input type="text" id="description" name="description" value="<?php echo htmlspecialchars($projet['description']); ?>" required>

        <label for="code_meuble">Code Meuble:</label>
        <input type="text" id="code_meuble" name="code_meuble" value="<?php echo htmlspecialchars($projet['code_meuble']); ?>" required>

        <label>Image actuelle:</label>
        <img src="<?php echo htmlspecialchars($projet['chemin_image']); ?>" alt="Image actuelle du projet">

        <label for="nouvelle_image">Uploader une nouvelle image (optionnel):</label>
        <input type="file" id="nouvelle_image" name="nouvelle_image" accept="image/*">

        <button type="submit">Sauvegarder</button>
        <a href="projets.php?client=<?php echo $clientIndex; ?>" style="margin-left: 10px; text-decoration: none;">Annuler</a>
    </form>
</body>
</html>