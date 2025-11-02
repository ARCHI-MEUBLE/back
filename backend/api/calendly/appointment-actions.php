<?php
/**
 * API Admin: Actions sur les rendez-vous
 * PUT /api/calendly/appointment-actions.php?id=X&action=cancel|complete|reschedule
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Récupérer les paramètres
        $appointmentId = $_GET['id'] ?? null;
        $action = $_GET['action'] ?? null;

        if (!$appointmentId || !$action) {
            http_response_code(400);
            echo json_encode(['error' => 'ID ou action manquant']);
            exit;
        }

        // Récupérer le rendez-vous
        $appointment = $db->queryOne(
            "SELECT * FROM calendly_appointments WHERE id = ?",
            [$appointmentId]
        );

        if (!$appointment) {
            http_response_code(404);
            echo json_encode(['error' => 'Rendez-vous introuvable']);
            exit;
        }

        switch ($action) {
            case 'cancel':
                // Annuler le rendez-vous
                $db->execute(
                    "UPDATE calendly_appointments SET status = 'cancelled' WHERE id = ?",
                    [$appointmentId]
                );
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Rendez-vous annulé avec succès',
                    'new_status' => 'cancelled'
                ]);
                break;

            case 'complete':
                // Marquer comme terminé
                $db->execute(
                    "UPDATE calendly_appointments SET status = 'completed' WHERE id = ?",
                    [$appointmentId]
                );
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Rendez-vous marqué comme terminé',
                    'new_status' => 'completed'
                ]);
                break;

            case 'reschedule':
                // Reprogrammer - récupérer la nouvelle date depuis le body
                $data = json_decode(file_get_contents('php://input'), true);
                $newStartTime = $data['start_time'] ?? null;
                $newEndTime = $data['end_time'] ?? null;

                if (!$newStartTime || !$newEndTime) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Nouvelles dates manquantes']);
                    exit;
                }

                $db->execute(
                    "UPDATE calendly_appointments SET start_time = ?, end_time = ?, status = 'scheduled' WHERE id = ?",
                    [$newStartTime, $newEndTime, $appointmentId]
                );
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Rendez-vous reprogrammé avec succès',
                    'new_status' => 'scheduled',
                    'new_start_time' => $newStartTime,
                    'new_end_time' => $newEndTime
                ]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Action invalide. Actions supportées: cancel, complete, reschedule']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
?>
