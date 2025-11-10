<?php
/**
 * Script de diagnostic - Lister les fichiers uploadés
 * URL: https://back-production-526e.up.railway.app/list_uploads.php
 */

header('Content-Type: application/json; charset=utf-8');

$uploadDir = '/data/uploads/models/';

$response = [
    'directory' => $uploadDir,
    'exists' => is_dir($uploadDir),
    'readable' => is_readable($uploadDir),
    'files' => [],
    'count' => 0,
    'total_size' => 0,
];

if ($response['exists'] && $response['readable']) {
    $files = scandir($uploadDir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $fullPath = $uploadDir . $file;
        $fileInfo = [
            'name' => $file,
            'size' => filesize($fullPath),
            'size_human' => round(filesize($fullPath) / 1024, 2) . ' KB',
            'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
            'url' => 'https://' . $_SERVER['HTTP_HOST'] . '/uploads/models/' . $file,
        ];

        $response['files'][] = $fileInfo;
        $response['total_size'] += $fileInfo['size'];
    }

    $response['count'] = count($response['files']);
    $response['total_size_human'] = round($response['total_size'] / 1024, 2) . ' KB';
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
