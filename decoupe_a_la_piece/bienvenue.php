<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue des Panneaux</title>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f5f5f5;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
            color: #333;
        }
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1200px;
            width: 100%;
        }
        .gallery-item {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            position: relative;
            text-align: center;
        }
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .gallery img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .rank {
            position: absolute;
            top: 10px;
            left: 10px;
            color: white;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1em;
            font-weight: bold;
            background-color: #27ae60;
        }
        .panel-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        .panel-thickness {
            font-size: 1em;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        .panel-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        .panel-button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <h1>Catalogue des Panneaux</h1>
    <p class="subtitle">Classés par prix au m² (du moins cher au plus cher)</p>

    <?php
    $jsonFile = './../textures/panneau.json';
    if (!file_exists($jsonFile)) {
        echo '<p class="error">❌ Fichier panneau.json introuvable.</p>';
        exit;
    }
    $jsonData = file_get_contents($jsonFile);
    $panneaux = json_decode($jsonData, true);
    if (!$panneaux) {
        echo '<p class="error">❌ Erreur de lecture du fichier JSON.</p>';
        exit;
    }
    usort($panneaux, function($a, $b) {
        return $a['prix_m2_ht'] <=> $b['prix_m2_ht'];
    });
    echo '<div class="gallery">';
    foreach ($panneaux as $index => $panneau) {
        $nom = $panneau['nom'];
        $epaisseur = $panneau['epaisseur'];
        $imagePath = "./../textures/{$nom}.png";
        echo '<div class="gallery-item">';
        echo '<div class="rank">' . ($index + 1) . '</div>';
        echo '<div class="panel-name">' . ucfirst(str_replace('_', ' ', $nom)) . '</div>';
        echo '<div class="panel-thickness">Épaisseur: ' . $epaisseur . ' mm</div>';
        if (file_exists($imagePath)) {
            echo '<img src="' . htmlspecialchars($imagePath, ENT_QUOTES) . '" alt="' . htmlspecialchars($nom, ENT_QUOTES) . '">';
        } else {
            echo '<div class="error">Image non disponible</div>';
        }
        echo '<form action="formulaire.php" method="POST">';
        echo '<input type="hidden" name="nom" value="' . htmlspecialchars($nom, ENT_QUOTES) . '">';
        echo '<input type="hidden" name="epaisseur" value="' . $epaisseur . '">';
        echo '<button type="submit" class="panel-button">Sélectionner</button>';
        echo '</form>';
        echo '</div>';
    }
    echo '</div>';
    ?>
</body>
</html>