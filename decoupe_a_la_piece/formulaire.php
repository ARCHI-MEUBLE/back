<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulaire Panneau</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            text-align: center;
            background-color: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: inline-block;
            text-align: left;
            width: 300px;
        }
        .title {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
        }
        img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .form-group {
            margin: 10px 0;
        }
    </style>
    <script>
        function updateForm() {
            let shape = document.getElementById('shape').value;
            document.getElementById('dimensions').innerHTML = '';
            if (shape === 'cercle') {
                document.getElementById('dimensions').innerHTML = '<label>Rayon (mm) :</label> <input type="number" name="rayon" required>';
            } else if (shape === 'rectangle') {
                document.getElementById('dimensions').innerHTML = '<label>Longueur (mm) :</label> <input type="number" name="longueur" required> <br> <label>Largeur (mm) :</label> <input type="number" name="largeur" required>';
            } else if (shape === 'carre') {
                document.getElementById('dimensions').innerHTML = '<label>Côté (mm) :</label> <input type="number" name="cote" required>';
            } else if (shape === 'polygone') {
                window.location.href = 'dessin.php';
            }
        }
    </script>
</head>
<body>
    <h1>Formulaire de Sélection</h1>
    
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $nom = htmlspecialchars($_POST['nom'] ?? 'Inconnu');
        $epaisseur = htmlspecialchars($_POST['epaisseur'] ?? 'N/A');
        $imagePath = "./../textures/{$nom}.png";
    
        echo '<div class="container">';
        echo '<p class="title">Panneau sélectionné :</p>';
        if (file_exists($imagePath)) {
            echo '<img src="' . htmlspecialchars($imagePath, ENT_QUOTES) . '" alt="' . htmlspecialchars($nom, ENT_QUOTES) . '">';
        } else {
            echo '<p>Image non disponible</p>';
        }
        echo '<p><strong>Nom :</strong> ' . ucfirst(str_replace('_', ' ', $nom)) . '</p>';
        echo '<p><strong>Épaisseur :</strong> ' . $epaisseur . ' mm</p>';
        echo '</div>';
    }
    ?>

    <h2>Choisissez la forme</h2>
    <form method="POST" action="traitement.php">
        <input type="hidden" name="nom" value="<?php echo $nom; ?>">
        <input type="hidden" name="epaisseur" value="<?php echo $epaisseur; ?>">
        <div class="form-group">
            <label>Forme :</label>
            <select id="shape" name="shape" onchange="updateForm()" required>
                <option value="">-- Sélectionnez --</option>
                <option value="cercle">Cercle</option>
                <option value="rectangle">Rectangle</option>
                <option value="carre">Carré</option>
                <option value="polygone">Polygone quelconque</option>
            </select>
        </div>
        <div id="dimensions" class="form-group"></div>
        <button type="submit">Valider</button>
    </form>
</body>
</html>