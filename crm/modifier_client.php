<?php
// Charger le fichier JSON
$file = 'data.json';

// Vérifier si le fichier existe et peut être lu
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true); // Convertir le JSON en tableau associatif
} else {
    die('Fichier JSON introuvable.');
}

// Récupérer l'index du client à modifier
if (!isset($_GET['client']) || !is_numeric($_GET['client'])) {
    die('Client invalide.');
}
$index = (int)$_GET['client'];

// Vérifier si l'index est valide
if (!isset($data['clients'][$index])) {
    die('Client introuvable.');
}

// Récupérer les informations du client
$client = $data['clients'][$index];

// Si le formulaire est soumis, mettre à jour les informations du client
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['clients'][$index]['nom'] = $_POST['nom'];
    $data['clients'][$index]['prenom'] = $_POST['prenom'];
    $data['clients'][$index]['numero'] = $_POST['numero'];
    $data['clients'][$index]['adresse_mail'] = $_POST['adresse_mail'];
    $data['clients'][$index]['adresse_postale'] = $_POST['adresse_postale'];

    // Sauvegarder les données mises à jour dans le fichier
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    // Rediriger vers la page des clients après la modification
    header('Location: clients.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Client</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            background: #fff;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        button, .btn {
            display: inline-block;
            padding: 10px 15px;
            margin-right: 10px;
            font-size: 14px;
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button {
            background-color: #4CAF50;
        }
        .btn.cancel {
            background-color: #f44336;
        }
        button:hover, .btn:hover {
            opacity: 0.9;
        }
        .btn.cancel:hover {
            background-color: #d32f2f;
        }
        .btn.add-client {
            margin-top: 20px;
            display: block;
            text-align: center;
            background-color: #007BFF;
        }
    </style>
</head>
<body>
    <h1>Modifier les Informations du Client</h1>

    <form method="POST">
        <label for="nom">Nom:</label>
        <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($client['nom']); ?>" required>

        <label for="prenom">Prénom:</label>
        <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($client['prenom']); ?>" required>

        <label for="numero">Numéro:</label>
        <input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($client['numero']); ?>" required>

        <label for="adresse_mail">Adresse Mail:</label>
        <input type="email" id="adresse_mail" name="adresse_mail" value="<?php echo htmlspecialchars($client['adresse_mail']); ?>" required>

        <label for="adresse_postale">Adresse Postale:</label>
        <input type="text" id="adresse_postale" name="adresse_postale" value="<?php echo htmlspecialchars($client['adresse_postale']); ?>" required>

        <button type="submit">Sauvegarder</button>
        <a href="clients.php" class="btn cancel">Annuler</a>
    </form>
</body>
</html>
