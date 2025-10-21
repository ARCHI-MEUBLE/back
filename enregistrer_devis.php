<?php
// Définir le dossier de destination
$jsonFile = 'devis.json';
$devisData = json_decode(file_get_contents($jsonFile), true);

$devisInfo = $devisData['DevisInfo'];
$numero = $devisInfo['NumeroDevis'];

$timestamp = date('YmdHis'); // Générer un horodatage unique
$dossierDestination = __DIR__ . "/devis/" . $numero;

// Créer le dossier s'il n'existe pas
if (!mkdir($dossierDestination, 0777, true)) {
    die("Échec de la création du dossier $dossierDestination");
}

// Fichiers à copier
$fichiers = [
    'meuble.glb',
    'meublep.glb',
    '03.php',
    'devis.json',
    'display_client.php',
    'logo.png'
];

// Copier les fichiers dans le nouveau dossier
foreach ($fichiers as $fichier) {
    $source = __DIR__ . "/$fichier";
    $destination = "$dossierDestination/$fichier";
    
    if (!file_exists($source)) {
        echo "Fichier manquant : $source\n";
        continue;
    }
    
    if (!copy($source, $destination)) {
        echo "Échec de la copie de $fichier\n";
    }
}

// Copier le dossier uploads
$sourceUploads = __DIR__ . "/uploads";
$destinationUploads = "$dossierDestination/uploads";

function copyDirectory($src, $dst) {
    $dir = opendir($src);
    mkdir($dst);
    while (($file = readdir($dir)) !== false) {
        if ($file !== '.' && $file !== '..') {
            $srcFile = "$src/$file";
            $dstFile = "$dst/$file";
            if (is_dir($srcFile)) {
                copyDirectory($srcFile, $dstFile);
            } else {
                copy($srcFile, $dstFile);
            }
        }
    }
    closedir($dir);
}

if (file_exists($sourceUploads)) {
    copyDirectory($sourceUploads, $destinationUploads);
} else {
    echo "Dossier uploads manquant\n";
}

echo "Devis enregistré dans archimeuble.com/gauthier/devis/$numero/display_client.php\n";

// Ouvrir un nouvel onglet avec la page de consultation
echo "<script>window.open('https://configurateur.archimeuble/devis/$numero/display_client.php', '_blank');</script>";
?>
