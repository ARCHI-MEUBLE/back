<?php
// Chemin du fichier JSON
$json_file = 'devis.json';

// Vérifier si le fichier existe
if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
    if ($data === null) {
        die("Erreur : impossible de lire le contenu de devis.json.");
    }
    
    // Ajouter ou mettre à jour la date du devis
    if (isset($data['DevisInfo'])) {
        $data['DevisInfo']['DateDevis'] = date('Y-m-d'); // Ajout de la date actuelle au format YYYY-MM-DD
    } else {
        $data['DevisInfo'] = [
            'DateDevis' => date('Y-m-d'),
        ];
    }


    
    // Générer un nouveau numéro de devis basé sur la date et l'heure
    $timestamp = date('YmdHis'); // Format YYYYMMDDHHMMSS
    $data['DevisInfo']['NumeroDevis'] = 'DEV-' . $timestamp;


    // Sauvegarder les modifications dans le fichier JSON
    file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
} else {
    die("Erreur : le fichier devis.json est introuvable.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            text-align: center;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #333;
        }
        p {
            margin: 15px 0;
            color: #555;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }
        a:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bienvenue dans Archi Creator</h1>
        <p>La date et le numéro du devis ont été mis à jour avec succès.</p>
        <a href="01.php">Aller à l'accueil</a>
    </div>
</body>
</html>

