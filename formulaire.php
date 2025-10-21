<?php
// Initialisation des variables
$nom = $prenom = $adresse = $mail = $telephone = "";
$image_path = "";

// Chemin du fichier JSON
$json_file = 'devis.json';

// Chemin du dossier d'upload pour les images
$image_upload_dir = 'uploads/';

// Créez le dossier si nécessaire
if (!is_dir($image_upload_dir)) {
    mkdir($image_upload_dir, 0777, true);
}

// Vérifiez si le fichier existe et chargez son contenu
if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
} else {
    die("Erreur : le fichier devis.json est introuvable.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération des données du formulaire
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $adresse = $_POST['adresse'];
    $mail = $_POST['mail'];
    $telephone = $_POST['telephone'];


    

    // Mise à jour des informations du client dans les données JSON
    $data['Client'] = [
        "Nom" => $nom,
        "Prenom" => $prenom,
        "Numero" => $telephone,
        "Mail" => $mail,
        "AdressePostale" => $adresse,
        "Image" => $image_path
    ];

    // Enregistrement des modifications dans le fichier JSON
    if (file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT))) {
        // Redirection vers display.php après mise à jour
        header("Location: 02.php");
        exit();
    } else {
        echo "<p>Erreur lors de la mise à jour du fichier JSON.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulaire de Contact</title>
    <style>
        /* Style similaire à votre version */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        label {
            display: block;
            margin: 8px 0;
            font-weight: bold;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Formulaire de Contact</h2>
        <form method="POST" enctype="multipart/form-data">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($nom) ?>" required>

            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($prenom) ?>" required>

            <label for="adresse">Adresse</label>
            <textarea id="adresse" name="adresse" required><?= htmlspecialchars($adresse) ?></textarea>

            <label for="mail">Email</label>
            <input type="email" id="mail" name="mail" value="<?= htmlspecialchars($mail) ?>" required>

            <label for="telephone">Numéro de téléphone</label>
            <input type="tel" id="telephone" name="telephone" value="<?= htmlspecialchars($telephone) ?>" required>



            <button type="submit">Envoyer</button>
        </form>
    </div>
</body>
</html>