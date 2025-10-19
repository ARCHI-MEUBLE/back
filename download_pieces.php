<?php
$zip = new ZipArchive();
$zipFile = 'pieces.zip';
$piecesDir = 'pieces';

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    // Add all files in the "pieces" directory
    $files = glob("$piecesDir/*");
    foreach ($files as $file) {
        if (is_file($file)) {
            $zip->addFile($file, basename($file));
        }
    }
    $zip->close();

    // Force download the ZIP file
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="pieces.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);

    // Clean up the temporary ZIP file
    unlink($zipFile);
} else {
    echo "Failed to create ZIP file.";
}
?>
