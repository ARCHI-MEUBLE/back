<?php

        $json = file_get_contents('produit.json');
        $data = json_decode($json, true);
        $prixHT = number_format($data['prixht'], 2, ',', ' ');
        $codeMeuble = $data['chaine'];
 


// Chemin du fichier JSON
$json_file = 'devis.json';

// Vérifiez si le fichier existe et chargez son contenu
if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
} else {
    die("Erreur : le fichier devis.json est introuvable.");
}

if (isset($_POST['nom'])) {
    $data['Client'] = [
        "Nom" => $_POST['nom'],
        "Prenom" => $_POST['prenom'] ?? $data['Client']['prenom'] ?? '',
        "Numero" => $_POST['telephone'] ?? $data['Client']['telephone'] ?? '',
        "Mail" => $_POST['mail'] ?? $data['Client']['mail'] ?? '',
        "AdressePostale" => $_POST['adresse'] ?? $data['Client']['adresse'] ?? ''
    ];
    file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
}
?>

<?php
$imagePath = '';
$logFile = 'log.txt'; // Fichier de log

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier et gérer l'upload de l'image
    $uploadDir = './uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageTmpPath = $_FILES['image']['tmp_name'];
        $imageName = basename($_FILES['image']['name']);
        $imagePath = $uploadDir . $imageName;

        if (move_uploaded_file($imageTmpPath, $imagePath)) {
            // Ajouter l'image au fichier produit.json
            $jsonFile = 'produit.json';
            if (file_exists($jsonFile)) {
                $jsonData = json_decode(file_get_contents($jsonFile), true);
                $jsonData['image'] = $imagePath;
                file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_PRINT));
            }
        } else {
            file_put_contents($logFile, "Erreur lors du téléchargement de l'image\n", FILE_APPEND);
        }
    }

    // Vérifier si inputString est défini
    if (isset($_POST['inputString'])) {
        $inputString = escapeshellarg($_POST['inputString']);
        $command = "python3 procedure.py $inputString";

        $output = [];
        $return_var = 0;
        exec($command . ' 2>&1', $output, $return_var);

        // Enregistrer la sortie et le code retour dans log.txt
        file_put_contents($logFile, "Commande exécutée : $command\n", FILE_APPEND);
        file_put_contents($logFile, "Sortie :\n" . implode("\n", $output) . "\n", FILE_APPEND);
        file_put_contents($logFile, "Code de retour : $return_var\n", FILE_APPEND);

        if ($return_var !== 0) {
            echo "<h2>Erreur lors de l'exécution du script Python :</h2>";
            echo "<p>" . htmlspecialchars(implode("\n", $output)) . "</p>";
            echo "<p>Code de retour : $return_var</p>";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisation d'un fichier GLB</title>
    <script type="module" src="https://unpkg.com/@google/model-viewer@3.4.0/dist/model-viewer.min.js"></script>
    <style>
        body { 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
             
            margin: 0; 
            background-color: #f0f0f0;
            font-family: Arial, sans-serif;
            padding: 25px;
            box-sizing: border-box;
        }
        model-viewer { 
            width: 100%;  
            height: 80vh; 
            margin-bottom: 20px;
        }
        .button-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 20px;
            width: 100%;
            max-width: 600px;
        }
        .button-container a, .button-container button {
            text-decoration: none;
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }
        .button-container a:hover, .button-container button:hover {
            background-color: #0056b3;
        }
        .switch-button {
            background-color: #28a745;
        }
        .switch-button:hover {
            background-color: #218838;
        }
        .add-to-cart {
            background-color: #28a745 !important;
        }
        .add-to-cart:hover {
            background-color: #218838 !important;
        }
        .empty-cart {
            background-color: #dc3545 !important;
        }
        .empty-cart:hover {
            background-color: #c82333 !important;
        }
        .devis {
            background-color: #024F82 !important;
        }
        .devis:hover {
            background-color: #023e68 !important;
        }
        form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        input[type="file"] {
            width: 100%;
            max-width: 300px;
        }
        .exposure-control {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
            width: 100%;
            max-width: 300px;
        }
        input[type="range"] {
            width: 100%;
            height: 8px;
            border-radius: 4px;
        }
        .exposure-label {
            min-width: 80px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<model-viewer 
    id="model-viewer" 
    src="meuble.glb?t=<?php echo time(); ?>" 
    alt="Visualisation du modèle 3D" 
    ar 
    ar-modes="scene-viewer webxr quick-look" 
    environment-image="neutral" 
    auto-rotate 
    camera-controls
    exposure="3">
</model-viewer>


    <div class="button-container">
        <button class="switch-button" id="switch-model">Changer de modèle</button>


        <a href="ajouteraupanier.php" class="add-to-cart">Ajouter au panier</a>
        <a href="enregistrer_devis.php" class="add-to-cart">Enregistrer</a>
        <a href="ajouterpresta.php">Ajouter une prestation</a>
        <a href="formulaire.php">Aller au formulaire</a>
        <a href="01.php">Ajouter un autre meuble</a>
        <a href="03.php" class="devis">Aller au devis</a>
        <a href="viderpanier.php" class="empty-cart">Vider panier</a>
        <a href="menuisier.php">Menuisier</a>
        <a href="crm/clients.php">CRM</a>
       <a href="formulaire_prix.php">facteur prix</a>

    <a href="acheter.php?code=<?php echo urlencode($codeMeuble); ?>" class="buy-button">Acheter - <?php echo $prixHT; ?>€ HT</a>


    <a href="./chatgpt.php">ArchiSmart</a>


<button id="capture-button">Capturer l'image</button>
<canvas id="canvas" style="display: none;"></canvas>

<script>
let toggle = false; // Variable pour alterner entre photo1 et photo2

document.getElementById('capture-button').addEventListener('click', async () => {
    const modelViewer = document.getElementById('model-viewer');
    const canvas = document.getElementById('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    
    const blob = await modelViewer.toBlob();
    const img = new Image();
    img.src = URL.createObjectURL(blob);
    img.onload = function() {
        canvas.width = img.width;
        canvas.height = img.height;
        
        // Assurer un fond blanc
        context.fillStyle = 'white';
        context.fillRect(0, 0, canvas.width, canvas.height);
        
        context.drawImage(img, 0, 0);
        canvas.toBlob((blob) => {
            const formData = new FormData();
            
            // Alterner entre "photo1.jpg" et "photo2.jpg"
            const fileName = toggle ? 'photo1.jpg' : 'photo2.jpg';
            toggle = !toggle; // Inverser la variable

            formData.append('image', blob, fileName);
            
            fetch('save_image.php', {
                method: 'POST',
                body: formData
            }).catch(error => {
                console.error('Erreur:', error);
            });
        }, 'image/jpeg');
    };
});
</script>







    </div>



    <form id="upload-form" method="POST" enctype="multipart/form-data" action="save_image.php">
        <input type="file" name="image" accept="image/*" required>
        <button type="submit">Envoyer</button>
    </form>


<script>
    document.getElementById('switch-model').addEventListener('click', function() {
        const modelViewer = document.getElementById('model-viewer');
        let currentSrc = modelViewer.getAttribute('src');

        // Vérification explicite des valeurs possibles
        if (currentSrc.includes('meuble.glb')) {
            modelViewer.setAttribute('src', 'meublep.glb');
        } else {
            modelViewer.setAttribute('src', 'meuble.glb');
        }
    });
</script>

</body>
</html>