<script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shape = $_POST['forme'] ?? '';
    $epaisseur = $_POST['epaisseur'] ?? '';
    $dimension1 = $_POST['dimension1'] ?? '';
    $dimension2 = $_POST['dimension2'] ?? '';
    $texturePath = $_POST['texture'] ?? '';
    $outputFile = 'output.glb';

    // Construction de la commande pour générer le fichier GLB avec Python
    if ($shape === 'cercle') {
        $command = "python3 generate.py $shape $epaisseur $dimension1 $texturePath $outputFile";
    } else {
        $command = "python3 generate.py $shape $epaisseur $dimension1 $dimension2 $texturePath $outputFile";
    }
    
    // Exécuter la commande
    exec($command, $output, $return_var);
    
    // Affichage de la sortie du script Python
    echo "<h3>Sortie du script Python :</h3>";
    echo "<pre>";
    foreach ($output as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo "</pre>";
    
    if ($return_var === 0) {
        echo "<p>✅ Modèle 3D généré avec succès :</p>";
        echo "<model-viewer 
                id='model-viewer' 
                src='objet.glb' 
                alt='Visualisation du modèle 3D' 
                ar 
                ar-modes='scene-viewer webxr quick-look' 
                environment-image='neutral' 
                auto-rotate 
                camera-controls
                exposure='3'>
              </model-viewer>";
    } else {
        echo "<p>❌ Erreur lors de la génération du modèle 3D.</p>";
    }
}
?>
