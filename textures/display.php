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
            margin: 30px 0 10px 0;
            text-align: center;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
            text-align: center;
            padding: 20px;
        }
        h1 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Catalogue des Panneaux</h1>
    <p class="subtitle">Classés par prix au m² (du moins cher au plus cher)</p>

    <?php
    // Chemin du fichier JSON
    $jsonFile = 'panneau.json';

    // Vérifier si le fichier JSON existe
    if (!file_exists($jsonFile)) {
        echo '<p class="error">❌ Fichier panneau.json introuvable.</p>';
        exit;
    }

    // Lire le fichier JSON
    $jsonData = file_get_contents($jsonFile);
    $panneaux = json_decode($jsonData, true);

    // Vérifier si le JSON est valide
    if (!$panneaux) {
        echo '<p class="error">❌ Erreur de lecture du fichier JSON.</p>';
        exit;
    }

    // Calculer le prix au m² pour chaque panneau
    foreach ($panneaux as &$panneau) {
        $panneau['prix_m2_calcule'] = $panneau['prix_panneau_ht'] / $panneau['surface_panneau'];
    }

    // Trier les panneaux par prix au m² calculé (ordre croissant)
    usort($panneaux, function($a, $b) {
        return $a['prix_m2_calcule'] <=> $b['prix_m2_calcule'];
    });

    echo '<div class="gallery">';

    foreach ($panneaux as $index => $panneau) {
        $nom = $panneau['nom'];
        $imagePath = "./{$nom}.png";

        echo '<div class="gallery-item">';
        
        // Numéro de classement
        echo '<div class="rank">' . ($index + 1) . '</div>';
        
        // Nom du panneau
        echo '<div class="panel-name">' . ucfirst(str_replace('_', ' ', $nom)) . '</div>';
        
        // Image
        if (file_exists($imagePath)) {
            echo '<img src="' . htmlspecialchars($imagePath, ENT_QUOTES) . '" alt="' . htmlspecialchars($nom, ENT_QUOTES) . '">';
        } else {
            echo '<div class="error">Image non disponible</div>';
        }
        
        echo '</div>';
    }

    echo '</div>';
    ?>

</body>
</html>