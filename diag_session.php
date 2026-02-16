<?php
/**
 * Script de diagnostic de session
 */
require_once __DIR__ . '/backend/config/cors.php';

header('Content-Type: text/plain');

echo "=== DIAGNOSTIC SESSION ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "\n";

echo "=== SESSION DATA ===\n";
if (empty($_SESSION)) {
    echo "La session est VIDE.\n";
} else {
    foreach ($_SESSION as $key => $value) {
        if ($key === 'password' || strpos($key, 'secret') !== false) {
            echo "$key: ********\n";
        } else {
            echo "$key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
    }
}
echo "\n";

echo "=== REQUEST DATA ===\n";
echo "Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'None') . "\n";
echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'None') . "\n";
echo "Cookie header: " . ($_SERVER['HTTP_COOKIE'] ?? 'None') . "\n";
echo "\n";

echo "=== DATABASE CHECK ===\n";
try {
    require_once __DIR__ . '/backend/core/Database.php';
    $db = Database::getInstance();
    $res = $db->queryOne("SELECT count(*) as total FROM configurations");
    echo "Connexion DB: OK\n";
    echo "Nombre de configurations: " . $res['total'] . "\n";
    
    if (isset($_SESSION['customer_id'])) {
        $user = $db->queryOne("SELECT email FROM customers WHERE id = ?", [$_SESSION['customer_id']]);
        if ($user) {
            echo "Utilisateur identifié en base: " . $user['email'] . "\n";
        } else {
            echo "ERREUR: ID utilisateur session (" . $_SESSION['customer_id'] . ") non trouvé en base.\n";
        }
    }
} catch (Exception $e) {
    echo "ERREUR DB: " . $e->getMessage() . "\n";
}
