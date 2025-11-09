<?php
/**
 * API Admin: Calendly Appointments
 * GET /api/calendly/appointments - Liste tous les rendez-vous Calendly
 * GET /api/calendly/appointments?status=scheduled - Filtrer par statut
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// L'authentification est gérée par Next.js API route
// Pas besoin de vérifier $_SESSION ici

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance()->getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Paramètres de filtrage et pagination
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Construction de la requête
        $sql = "SELECT * FROM calendly_appointments";
        $params = [];

        if ($status) {
            $sql .= " WHERE status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY start_time DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);

        // Bind des paramètres
        if ($status) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Compter le nombre total de rendez-vous
        $countSql = "SELECT COUNT(*) as total FROM calendly_appointments";
        if ($status) {
            $countSql .= " WHERE status = :status";
        }

        $countStmt = $db->prepare($countSql);
        if ($status) {
            $countStmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Formater les dates pour l'affichage
        foreach ($appointments as &$appointment) {
            try {
                $startDateTime = new DateTime($appointment['start_time']);
                $endDateTime = new DateTime($appointment['end_time']);

                $appointment['formatted_start'] = $startDateTime->format('d/m/Y à H:i');
                $appointment['formatted_end'] = $endDateTime->format('H:i');
                $appointment['formatted_date'] = $startDateTime->format('d/m/Y');
                $appointment['formatted_time'] = $startDateTime->format('H:i') . ' - ' . $endDateTime->format('H:i');
            } catch (Exception $e) {
                $appointment['formatted_start'] = $appointment['start_time'];
                $appointment['formatted_end'] = $appointment['end_time'];
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'appointments' => $appointments,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
?>
