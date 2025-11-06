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
if (!$calendlyToken || empty(trim($calendlyToken))) {
    error_log("Warning: CALENDLY_API_TOKEN not configured - cannot fetch detailed appointment info");
    http_response_code(500);
    echo json_encode([
        'error' => 'Calendly API token not configured',
        'message' => 'Veuillez configurer CALENDLY_API_TOKEN dans le fichier .env',
        'instructions' => 'Obtenez votre token sur: https://calendly.com/integrations/api_webhooks'
    ]);
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

// Log de la structure complète pour debug
error_log("Calendly invitee resource: " . json_encode($resource, JSON_PRETTY_PRINT));

// Extraire les informations nécessaires
$name = $resource['name'] ?? 'Client';
$email = $resource['email'] ?? '';
$eventUri = $resource['event'] ?? '';
$timezone = $resource['timezone'] ?? 'Europe/Paris';

// Récupérer le numéro de téléphone depuis les champs standard de Calendly
$phoneNumber = '';
if (isset($resource['text_reminder_number']) && !empty($resource['text_reminder_number'])) {
    $phoneNumber = $resource['text_reminder_number'];
    error_log("Phone from text_reminder_number: $phoneNumber");
}

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
$meetingUrl = $eventResource['location']['join_url'] ?? null;

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
    // Log des questions/réponses pour debug
    error_log("Calendly Q&A: " . json_encode($resource['questions_and_answers']));

    foreach ($resource['questions_and_answers'] as $qa) {
        $question = strtolower($qa['question'] ?? '');
        $answer = $qa['answer'] ?? '';

        if (stripos($question, 'configuration') !== false || stripos($question, 'lien') !== false) {
            $configUrl = $answer;
        }
        if (stripos($question, 'note') !== false || stripos($question, 'information') !== false) {
            $additionalNotes = $answer;
        }
        // Récupérer le numéro de téléphone si présent (plusieurs variantes)
        // Seulement si pas déjà trouvé dans text_reminder_number
        if (empty($phoneNumber) && (
            stripos($question, 'téléphone') !== false ||
            stripos($question, 'phone') !== false ||
            stripos($question, 'numero') !== false ||
            stripos($question, 'numéro') !== false ||
            stripos($question, 'portable') !== false ||
            stripos($question, 'mobile') !== false ||
            stripos($question, 'contact') !== false ||
            stripos($question, 'tel') !== false)) {
            $phoneNumber = $answer;
            error_log("Phone number found in Q&A: $phoneNumber");
        }
    }
} else {
    error_log("No questions_and_answers in Calendly response");
}

// Détecter si c'est un rendez-vous téléphonique
$isPhoneAppointment = (stripos($eventType, 'téléphone') !== false || stripos($eventType, 'phone') !== false || stripos($eventType, 'appel') !== false);

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

    // Auto-migration: Ajouter les colonnes meeting_url et phone_number si elles n'existent pas
    $tableInfo = $db->query("PRAGMA table_info(calendly_appointments)")->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($tableInfo, 'name');

    if (!in_array('meeting_url', $columns)) {
        $db->exec("ALTER TABLE calendly_appointments ADD COLUMN meeting_url TEXT");
        $logEntry = sprintf("[%s] Auto-migration: Added meeting_url column\n", $timestamp);
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    if (!in_array('phone_number', $columns)) {
        $db->exec("ALTER TABLE calendly_appointments ADD COLUMN phone_number TEXT");
        $logEntry = sprintf("[%s] Auto-migration: Added phone_number column\n", $timestamp);
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    // Insertion du rendez-vous
    $stmt = $db->prepare("
        INSERT OR REPLACE INTO calendly_appointments
        (calendly_event_id, client_name, client_email, event_type, start_time, end_time, timezone, config_url, additional_notes, meeting_url, phone_number, status, confirmation_sent)
        VALUES (:event_id, :name, :email, :event_type, :start_time, :end_time, :timezone, :config_url, :notes, :meeting_url, :phone_number, 'scheduled', 1)
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
        ':notes' => $additionalNotes,
        ':meeting_url' => $meetingUrl,
        ':phone_number' => $phoneNumber
    ]);

    $logEntry = sprintf("[%s] Appointment saved to database: %s (%s) with meeting URL: %s\n", $timestamp, $name, $email, $meetingUrl ?: 'none');
    file_put_contents($logFile, $logEntry, FILE_APPEND);

} catch (PDOException $e) {
    $errorLog = sprintf("[%s] Database Error: %s\n", $timestamp, $e->getMessage());
    file_put_contents($logFile, $errorLog, FILE_APPEND);

    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
    exit();
}

// Envoi des emails de confirmation (pour tous les types de rendez-vous)
try {
    require_once __DIR__ . '/EmailService.php';
    $emailService = new EmailService();

    // Email de confirmation au client
    $clientEmailSent = $emailService->sendConfirmationEmail(
        $email,
        $name,
        $eventType,
        $formattedStart,
        $formattedEnd,
        $configUrl,
        $meetingUrl
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
        $additionalNotes . ($isPhoneAppointment && $phoneNumber ? "\nNuméro de téléphone: $phoneNumber" : '')
    );

    if ($adminEmailSent) {
        $logEntry = sprintf("[%s] Admin notification sent for appointment with %s (%s)\n", $timestamp, $name, $email);
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    } else {
        $errorLog = sprintf("[%s] Failed to send admin notification\n", $timestamp);
        file_put_contents($logFile, $errorLog, FILE_APPEND);
    }

    // Créer une notification admin dans le système
    try {
        require_once __DIR__ . '/../../models/AdminNotification.php';
        $adminNotification = new AdminNotification();

        $notificationType = $isPhoneAppointment ? 'calendly_phone' : 'calendly_video';
        $notificationMessage = sprintf(
            "Nouveau rendez-vous %s - %s (%s) prévu le %s",
            $isPhoneAppointment ? 'téléphonique' : 'visio',
            $name,
            $email,
            $formattedStart
        );

        $adminNotification->create($notificationType, $notificationMessage, $db->lastInsertId());

        $logEntry = sprintf("[%s] Admin notification created in dashboard for %s (%s)\n", $timestamp, $name, $email);
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    } catch (Exception $e) {
        $errorLog = sprintf("[%s] Failed to create admin notification: %s\n", $timestamp, $e->getMessage());
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
            'phone_number' => $phoneNumber ?: null,
            'event_type' => $eventType,
            'start_time' => $formattedStart,
            'end_time' => $formattedEnd,
            'is_phone_appointment' => $isPhoneAppointment,
            'client_email_sent' => $clientEmailSent,
            'admin_email_sent' => $adminEmailSent
        ]
    ]);

} catch (Exception $e) {
    $errorLog = sprintf("[%s] Error sending emails (SMTP not configured): %s\n", $timestamp, $e->getMessage());
    file_put_contents($logFile, $errorLog, FILE_APPEND);

    // Retourner un succès quand même car le rendez-vous est enregistré
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Rendez-vous enregistré (emails non envoyés - SMTP non configuré)',
        'data' => [
            'name' => $name,
            'email' => $email,
            'event_type' => $eventType,
            'start_time' => $formattedStart,
            'emails_sent' => false,
            'email_error' => $e->getMessage()
        ]
    ]);
}
?>
