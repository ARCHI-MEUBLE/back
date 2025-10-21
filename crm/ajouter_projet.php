<?php
// Charger le fichier JSON
$file = 'data.json';

// Vérifier si le fichier existe et peut être lu
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true); // Convertir le JSON en tableau associatif
} else {
    // Si le fichier n'existe pas, initialiser un tableau vide
    $data = ['clients' => []];
}

// Vérifier si un client a été spécifié dans l'URL
if (isset($_GET['client']) && isset($data['clients'][$_GET['client']])) {
    $clientIndex = $_GET['client'];
    $client = $data['clients'][$clientIndex]; // Récupérer les données du client
} else {
    // Si aucun client n'est spécifié ou si l'index est invalide, rediriger vers la page des clients
    header('Location: clients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $projet = [
        'titre' => $_POST['titre'],
        'description' => $_POST['description'],
        'code_meuble' => $_POST['code_meuble'] // Nouveau champ Code meuble
    ];

    // Upload de l'image
    if (isset($_FILES['chemin_image']) && $_FILES['chemin_image']['error'] == 0) {
        $uploadDir = './uploads/';
        $fileName = basename($_FILES['chemin_image']['name']);
        $uploadFilePath = $uploadDir . $fileName;

        // Déplacer l'image téléchargée dans le dossier d'upload
        if (move_uploaded_file($_FILES['chemin_image']['tmp_name'], $uploadFilePath)) {
            $projet['chemin_image'] = $uploadFilePath;
        } else {
            echo "Erreur lors du téléchargement de l'image.";
            exit;
        }
    }

    // Ajouter le projet au client
    $data['clients'][$clientIndex]['projets'][] = $projet;

    // Sauvegarder les données mises à jour dans le fichier JSON
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    // Rediriger vers la page des projets du client après l'ajout
    header("Location: projets.php?client=$clientIndex");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Projet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        form {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin: 10px 0 5px;
            font-size: 1rem;
            color: #333;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        .back-link {
            text-align: center;
            display: block;
            margin-top: 20px;
            font-size: 1rem;
            text-decoration: none;
            color: #007BFF;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Ajouter un Projet pour <?php echo htmlspecialchars($client['prénom']) . ' ' . htmlspecialchars($client['nom']); ?></h1>

    <form action="ajouter_projet.php?client=<?php echo $clientIndex; ?>" method="POST" enctype="multipart/form-data">
        <label for="titre">Titre du Projet :</label>
        <input type="text" id="titre" name="titre" required>

        <label for="description">Description :</label>
        <textarea id="description" name="description" rows="4" required></textarea>

        <label for="code_meuble">Code Meuble :</label>
        <input type="text" id="code_meuble" name="code_meuble" required>

        <label for="chemin_image">Image du Projet :</label>
        <input type="file" id="chemin_image" name="chemin_image" accept="image/*" >

        <input type="submit" value="Ajouter Projet">
    </form>

    <a href="projets.php?client=<?php echo $clientIndex; ?>" class="back-link">Retour aux projets</a>
</body>
</html>
