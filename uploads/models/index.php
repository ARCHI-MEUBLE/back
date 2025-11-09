<?php
/**
 * Servir les images uploadées depuis /data/uploads/models/
 */

// Récupérer le nom du fichier depuis l'URL
$requestUri = $_SERVER['REQUEST_URI'];
$pattern = '/\/uploads\/models\/([^\/\?]+)/';

if (preg_match($pattern, $requestUri, $matches)) {
    $filename = $matches[1];

    // Chemin complet du fichier
    $filepath = '/data/uploads/models/' . $filename;

    // Vérifier si le fichier existe
    if (file_exists($filepath) && is_file($filepath)) {
        // Déterminer le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        // Headers pour l'image
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: public, max-age=31536000'); // Cache 1 an

        // Envoyer le fichier
        readfile($filepath);
        exit;
    }
}

// Fichier non trouvé
http_response_code(404);
echo '404 - Image not found';
