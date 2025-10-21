<?php
/**
 * Endpoint de test pour vérifier que POST JSON fonctionne
 */

header('Content-Type: application/json');

echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'raw_input' => file_get_contents('php://input'),
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST
]);
