<?php
/**
 * ArchiMeuble - Endpoint public des réalisations
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Realisation.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$realisation = new Realisation();
$data = $realisation->getAll();

echo json_encode(['success' => true, 'realisations' => $data]);
?>
