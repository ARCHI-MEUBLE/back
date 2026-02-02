<?php
/**
 * Liste les clients pour debug
 */
require_once __DIR__ . '/backend/core/Database.php';

header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    $customers = $db->query("SELECT id, email, first_name, last_name, created_at FROM customers ORDER BY created_at DESC");
    
    echo "Nombre total de clients: " . count($customers) . "\n\n";
    echo str_pad("ID", 5) . " | " . str_pad("Email", 30) . " | " . str_pad("Nom", 30) . " | " . "Date\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($customers as $c) {
        echo str_pad($c['id'], 5) . " | " . 
             str_pad($c['email'], 30) . " | " . 
             str_pad($c['first_name'] . " " . $c['last_name'], 30) . " | " . 
             $c['created_at'] . "\n";
    }
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage();
}
