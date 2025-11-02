<?php
/**
 * API pour envoyer un email de confirmation après réservation Calendly
 * Appelé directement depuis le frontend après qu'un client ait réservé
 */

require_once __DIR__ . '/EmailService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion de la requête OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Seules les requêtes POST sont autorisées
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Récupération du payload JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

// Validation des données requises
$requiredFields = ['name', 'email', 'event_type', 'start_time', 'end_time'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

// Extraction des données
$name = $data['name'];
$email = $data['email'];
$eventType = $data['event_type'];
$startTime = $data['start_time'];
$endTime = $data['end_time'];
$timezone = $data['timezone'] ?? 'Europe/Paris';
$configUrl = $data['config_url'] ?? '';
$additionalNotes = $data['notes'] ?? '';
$calendlyEventId = $data['event_uri'] ?? uniqid('calendly_', true);

// Formatage de la date et heure
try {
    $startDateTime = new DateTime($startTime);
    $endDateTime = new DateTime($endTime);
    $startDateTime->setTimezone(new DateTimeZone($timezone));
    $endDateTime->setTimezone(new DateTimeZone($timezone));

    $formattedStart = $startDateTime->format('d/m/Y à H:i');
    $formattedEnd = $endDateTime->format('H:i');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit();
}

// Détection de l'environnement (Docker vs local)
$isDocker = file_exists('/app');
$dbPath = $isDocker ? '/app/database/archimeuble.db' : __DIR__ . '/../../database/archimeuble.db';

// Configuration du fichier de log
$logFile = $isDocker ? '/app/logs/calendly.log' : __DIR__ . '/../../logs/calendly.log';
$logDir = dirname($logFile);

// Création du dossier logs si nécessaire
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$timestamp = date('Y-m-d H:i:s');

// Enregistrement du rendez-vous dans la base de données
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Insertion du rendez-vous
    $stmt = $db->prepare("
        INSERT OR REPLACE INTO calendly_appointments
        (calendly_event_id, client_name, client_email, event_type, start_time, end_time, timezone, config_url, additional_notes, status, confirmation_sent)
        VALUES (:event_id, :name, :email, :event_type, :start_time, :end_time, :timezone, :config_url, :notes, 'scheduled', 1)
    ");

    $stmt->execute([
        ':event_id' => $calendlyEventId,
        ':name' => $name,
        ':email' => $email,
        ':event_type' => $eventType,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':timezone' => $timezone,
        ':config_url' => $configUrl,
        ':notes' => $additionalNotes
    ]);

    $logEntry = sprintf("[%s] Appointment saved to database: %s (%s)\n", $timestamp, $name, $email);
    file_put_contents($logFile, $logEntry, FILE_APPEND);

} catch (PDOException $e) {
    $errorLog = sprintf("[%s] Database Error: %s\n", $timestamp, $e->getMessage());
    file_put_contents($logFile, $errorLog, FILE_APPEND);

    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
    exit();
}

// Envoi des emails
try {
    $emailService = new EmailService();

    // Email de confirmation au client
    $clientEmailSent = $emailService->sendConfirmationEmail(
        $email,
        $name,
        $eventType,
        $formattedStart,
        $formattedEnd,
        $configUrl
    );

    if ($clientEmailSent) {
        $logEntry = sprintf("[%s] Confirmation email sent to client: %s (%s)\n", $timestamp, $name, $email);
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    } else {
        $errorLog = sprintf("[%s] Failed to send confirmation email to client: %s (%s)\n", $timestamp, $name, $email);
        file_put_contents($logFile, $errorLog, FILE_APPEND);
    }

    // Email de notification à l'administrateur
    $adminEmailSent = $emailService->sendAdminNotification(
        $name,
        $email,
        $eventType,
        $formattedStart,
        $formattedEnd,
        $configUrl,
        $additionalNotes
    );

    if ($adminEmailSent) {
        $logEntry = sprintf("[%s] Admin notification sent for appointment with %s (%s)\n", $timestamp, $name, $email);
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    } else {
        $errorLog = sprintf("[%s] Failed to send admin notification\n", $timestamp);
        file_put_contents($logFile, $errorLog, FILE_APPEND);
    }

    // Réponse de succès
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Emails de confirmation envoyés',
        'data' => [
            'name' => $name,
            'email' => $email,
            'event_type' => $eventType,
            'start_time' => $formattedStart,
            'end_time' => $formattedEnd,
            'client_email_sent' => $clientEmailSent,
            'admin_email_sent' => $adminEmailSent
        ]
    ]);

} catch (Exception $e) {
    $errorLog = sprintf("[%s] Error sending emails: %s\n", $timestamp, $e->getMessage());
    file_put_contents($logFile, $errorLog, FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Email sending failed',
        'details' => $e->getMessage()
    ]);
}
?>
