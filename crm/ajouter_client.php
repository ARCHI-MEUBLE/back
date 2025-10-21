<?php
// Vérifier si le fichier JSON existe
$file = 'data.json';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $client = [
        'nom' => $_POST['nom'],
        'prenom' => $_POST['prenom'],
        'numero' => $_POST['numero'],
        'adresse_mail' => $_POST['adresse_mail'],
        'adresse_postale' => $_POST['adresse_postale'],
        'projets' => [] // Initialement, aucun projet
    ];

    // Vérifier si le fichier existe et peut être lu
    if (file_exists($file)) {
        // Charger le contenu du fichier JSON
        $json = file_get_contents($file);
        $data = json_decode($json, true); // Convertir le JSON en tableau associatif

        // Ajouter le nouveau client au tableau des clients
        $data['clients'][] = $client;
    } else {
        // Si le fichier n'existe pas, initialiser une structure de données vide
        $data = ['clients' => [$client]];
    }

    // Sauvegarder les données mises à jour dans le fichier JSON
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    // Rediriger vers la page des clients après ajout
    header('Location: clients.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Client</title>
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
            margin-bottom: 20px;
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

        input[type="text"],
        input[type="email"] {
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
    <h1>Ajouter un Client</h1>
    
    <form action="ajouter_client.php" method="POST">
        <label for="nom">Nom :</label><br>
        <input type="text" id="nom" name="nom" required><br><br>
        
        <label for="prenom">Prénom :</label><br>
        <input type="text" id="prenom" name="prenom" required><br><br>
        
        <label for="numero">Numéro :</label><br>
        <input type="text" id="numero" name="numero" required><br><br>
        
        <label for="adresse_mail">Adresse Email :</label><br>
        <input type="email" id="adresse_mail" name="adresse_mail" required><br><br>
        
        <label for="adresse_postale">Adresse Postale :</label><br>
        <input type="text" id="adresse_postale" name="adresse_postale" required><br><br>
        
        <input type="submit" value="Ajouter Client">
    </form>

    <br>
    <a href="clients.php" class="back-link">Retour à la liste des clients</a>
</body>
</html>
