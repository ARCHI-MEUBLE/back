<?php
/**
 * Endpoint HTTP pour déclencher l'envoi des rappels Calendly
 *
 * Protégé par un token secret pour éviter les appels non autorisés
 *
 * Usage :
 * GET https://votre-domaine.com/backend/api/calendly/trigger-reminders.php?token=VOTRE_SECRET
 *
 * À appeler toutes les 15 minutes via un service externe comme cron-job.org
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Récupérer le token secret depuis les variables d'environnement
$expectedToken = getenv('CRON_SECRET');

if (!$expectedToken) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'CRON_SECRET not configured',
        'message' => 'Le token secret n\'est pas configuré dans les variables d\'environnement'
    ]);
    exit();
}

// Vérifier le token fourni dans l'URL
$providedToken = $_GET['token'] ?? '';

if ($providedToken !== $expectedToken) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Token invalide ou manquant'
    ]);
    exit();
}

// Token valide, exécuter le script de rappels
try {
    // Exécuter le script via exec() pour éviter les conflits avec exit()
    $scriptPath = __DIR__ . '/send_reminders.php';
    $command = "php " . escapeshellarg($scriptPath) . " 2>&1";

    exec($command, $output, $returnCode);

    $outputText = implode("\n", $output);

    if ($returnCode === 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Reminder check completed',
            'timestamp' => date('Y-m-d H:i:s'),
            'output' => $outputText
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Script execution failed',
            'return_code' => $returnCode,
            'output' => $outputText
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Execution failed',
        'message' => $e->getMessage()
    ]);
}
?>
