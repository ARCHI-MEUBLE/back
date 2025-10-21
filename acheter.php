<?php
    $codeMeuble = isset($_GET['code']) ? htmlspecialchars($_GET['code']) : 'Non spécifié';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achat - Meuble</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            max-width: 600px;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h2 {
            color: #333;
        }
        p {
            font-size: 16px;
            color: #555;
        }
        strong {
            color: #222;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Pour toute demande, adressez-vous directement à notre commercial</h2>
        <p><strong>Téléphone :</strong> 0601062867</p>
        <p><strong>Email :</strong> pro.archimeuble@gmail.com</p>
        <p><strong>Commercial :</strong> Gauthier Hue</p>
        <p><strong>Code Meuble :</strong> <?php echo $codeMeuble; ?></p>
        <p>Nous reviendrons vers vous dans les plus brefs délais.</p>
    </div>
</body>
</html>
