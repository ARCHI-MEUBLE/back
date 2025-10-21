<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $uploadDir = './uploads/';
    
    // Créer le dossier si inexistant
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Déterminer quel fichier enregistrer en vérifiant le dernier fichier écrit
    $photo1 = $uploadDir . 'photo1.png';
    $photo2 = $uploadDir . 'photo2.png';
    
    // Vérifier l'existence des fichiers et alterner
    if (file_exists($photo1) && (!file_exists($photo2) || filemtime($photo1) > filemtime($photo2))) {
        $filePath = $photo2;
    } else {
        $filePath = $photo1;
    }

    // Enregistrer le fichier
    if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
        // Redirection vers 02.php après un court délai
        header("Location: 02.php");
        exit();
    } else {
        echo "Erreur lors de l'enregistrement de l'image.";
    }
}
?>
