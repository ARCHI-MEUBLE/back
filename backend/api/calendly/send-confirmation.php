<?php
/**
 * API pour envoyer un email de confirmation après réservation Calendly
 * Appelé directement depuis le frontend après qu'un client ait réservé
 */

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Log du payload reçu pour débogage
error_log("Calendly API - Payload reçu: " . $input);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON', 'received' => $input]);
    exit();
}

// Vérifier qu'on a bien l'URI de l'invité
if (!isset($data['invitee_uri']) || empty($data['invitee_uri'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing invitee_uri', 'received_data' => array_keys($data)]);
    exit();
}

// Récupérer le token API Calendly depuis les variables d'environnement
$calendlyToken = getenv('CALENDLY_API_TOKEN');
if (!$calendlyToken) {
    http_response_code(500);
    echo json_encode(['error' => 'Calendly API token not configured']);
    exit();
}

// Récupérer les informations de l'invité via l'API Calendly
$inviteeUri = $data['invitee_uri'];
$ch = curl_init($inviteeUri);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $calendlyToken,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("Calendly API Error: HTTP $httpCode - $response");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch invitee data from Calendly', 'http_code' => $httpCode]);
    exit();
}

$inviteeData = json_decode($response, true);
if (!$inviteeData || !isset($inviteeData['resource'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from Calendly API']);
    exit();
}

$resource = $inviteeData['resource'];

// Extraire les informations nécessaires
$name = $resource['name'] ?? 'Client';
$email = $resource['email'] ?? '';
$eventUri = $resource['event'] ?? '';
$timezone = $resource['timezone'] ?? 'Europe/Paris';

if (empty($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'No email found in Calendly invitee data']);
    exit();
}

// Récupérer les informations de l'événement
if (empty($eventUri)) {
    http_response_code(400);
    echo json_encode(['error' => 'No event URI found in invitee data']);
    exit();
}

$ch = curl_init($eventUri);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $calendlyToken,
    'Content-Type: application/json'
]);

$eventResponse = curl_exec($ch);
$eventHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($eventHttpCode !== 200) {
    error_log("Calendly Event API Error: HTTP $eventHttpCode - $eventResponse");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch event data from Calendly', 'http_code' => $eventHttpCode]);
    exit();
}

$eventData = json_decode($eventResponse, true);
if (!$eventData || !isset($eventData['resource'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid event response from Calendly API']);
    exit();
}

$eventResource = $eventData['resource'];
$eventType = $eventResource['name'] ?? 'Rendez-vous';
$startTime = $eventResource['start_time'] ?? '';
$endTime = $eventResource['end_time'] ?? '';

if (empty($startTime) || empty($endTime)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event times in Calendly data']);
    exit();
}

// Les données sont déjà extraites de l'API Calendly ci-dessus
// Récupérer les questions/réponses personnalisées si présentes
$configUrl = '';
$additionalNotes = '';

if (isset($resource['questions_and_answers'])) {
    foreach ($resource['questions_and_answers'] as $qa) {
        $question = strtolower($qa['question'] ?? '');
        $answer = $qa['answer'] ?? '';

        if (stripos($question, 'configuration') !== false || stripos($question, 'lien') !== false) {
            $configUrl = $answer;
        }
        if (stripos($question, 'note') !== false || stripos($question, 'information') !== false) {
            $additionalNotes = $answer;
        }
    }
}

$calendlyEventId = $inviteeUri;

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
