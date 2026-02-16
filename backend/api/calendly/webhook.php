<?php
/**
 * Webhook Calendly pour ArchiMeuble
 *
 * Ce fichier reçoit les événements Calendly (création de rendez-vous, annulations, etc.)
 * et les traite pour notifier le menuisier et le client, et conserver un historique.
 *
 * URL du webhook à configurer dans Calendly :
 * https://votre-domaine.com/api/calendly/webhook.php
 */

require_once __DIR__ . '/EmailService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Calendly-Webhook-Signature');

require_once __DIR__ . '/../../core/Database.php';

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
$event = json_decode($input, true);

if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

// Configuration du fichier de log
$logFile = __DIR__ . '/../../logs/calendly.log';
$logDir = dirname($logFile);

// Création du dossier logs si nécessaire
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Enregistrement de l'événement dans le log
$timestamp = date('Y-m-d H:i:s');
$logEntry = sprintf(
    "[%s] Event: %s | Data: %s\n",
    $timestamp,
    $event['event'] ?? 'unknown',
    json_encode($event, JSON_PRETTY_PRINT)
);

file_put_contents($logFile, $logEntry, FILE_APPEND);

// Traitement de l'événement invitee.created (nouveau rendez-vous créé)
if (isset($event['event']) && $event['event'] === 'invitee.created') {
    $payload = $event['payload'] ?? [];

    // Extraction des informations du rendez-vous
    $name = $payload['name'] ?? 'N/A';
    $email = $payload['email'] ?? 'N/A';
    $eventType = $payload['event_type_name'] ?? 'N/A';
    $startTime = $payload['scheduled_event']['start_time'] ?? 'N/A';
    $endTime = $payload['scheduled_event']['end_time'] ?? 'N/A';
    $timezone = $payload['timezone'] ?? 'Europe/Paris';
    $calendlyEventId = $payload['event']['uri'] ?? uniqid('calendly_', true);

    // Formatage de la date et heure
    $startDateTime = new DateTime($startTime);
    $endDateTime = new DateTime($endTime);
    $startDateTime->setTimezone(new DateTimeZone($timezone));
    $endDateTime->setTimezone(new DateTimeZone($timezone));

    $formattedStart = $startDateTime->format('d/m/Y à H:i');
    $formattedEnd = $endDateTime->format('H:i');

    // Extraction du lien de configuration (si fourni dans les réponses)
    $configUrl = '';
    $additionalNotes = '';

    if (isset($payload['questions_and_answers'])) {
        foreach ($payload['questions_and_answers'] as $qa) {
            $question = strtolower($qa['question'] ?? '');
            $answer = $qa['answer'] ?? '';

            // Recherche d'un lien de configuration
            if (stripos($question, 'configuration') !== false || stripos($question, 'lien') !== false) {
                $configUrl = $answer;
            }

            // Récupération des notes supplémentaires
            if (stripos($question, 'note') !== false || stripos($question, 'information') !== false) {
                $additionalNotes = $answer;
            }
        }
    }

    // Connexion à la base de données et enregistrement du rendez-vous
    try {
        $db = Database::getInstance()->getPDO();

        // Insertion du rendez-vous dans la base de données
        $stmt = $db->prepare("
            INSERT INTO calendly_appointments
            (calendly_event_id, client_name, client_email, event_type, start_time, end_time, timezone, config_url, additional_notes, status, confirmation_sent)
            VALUES (:event_id, :name, :email, :event_type, :start_time, :end_time, :timezone, :config_url, :notes, 'scheduled', TRUE)
            ON CONFLICT (calendly_event_id) DO UPDATE SET
                client_name = EXCLUDED.client_name,
                client_email = EXCLUDED.client_email,
                event_type = EXCLUDED.event_type,
                start_time = EXCLUDED.start_time,
                end_time = EXCLUDED.end_time,
                timezone = EXCLUDED.timezone,
                config_url = EXCLUDED.config_url,
                additional_notes = EXCLUDED.additional_notes,
                status = EXCLUDED.status,
                confirmation_sent = EXCLUDED.confirmation_sent
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

    } catch (PDOException $e) {
        $errorLog = sprintf(
            "[%s] Database Error: %s\n",
            $timestamp,
            $e->getMessage()
        );
        file_put_contents($logFile, $errorLog, FILE_APPEND);
    }

    // Envoi des emails (optionnel - ne bloque pas l'enregistrement)
    try {
        $emailService = new EmailService();

        // Email de confirmation au client
        try {
            $emailService->sendConfirmationEmail(
                $email,
                $name,
                $eventType,
                $formattedStart,
                $formattedEnd,
                $configUrl
            );

            $emailLog = sprintf(
                "[%s] Confirmation email sent to client: %s (%s)\n",
                $timestamp,
                $name,
                $email
            );
            file_put_contents($logFile, $emailLog, FILE_APPEND);
        } catch (Exception $e) {
            $errorLog = sprintf(
                "[%s] Error sending confirmation email: %s\n",
                $timestamp,
                $e->getMessage()
            );
            file_put_contents($logFile, $errorLog, FILE_APPEND);
        }

        // Email de notification à l'administrateur
        try {
            $emailService->sendAdminNotification(
                $name,
                $email,
                $eventType,
                $formattedStart,
                $formattedEnd,
                $configUrl,
                $additionalNotes
            );

            $emailLog = sprintf(
                "[%s] Admin notification sent for appointment with %s (%s)\n",
                $timestamp,
                $name,
                $email
            );
            file_put_contents($logFile, $emailLog, FILE_APPEND);
        } catch (Exception $e) {
            $errorLog = sprintf(
                "[%s] Error sending admin notification: %s\n",
                $timestamp,
                $e->getMessage()
            );
            file_put_contents($logFile, $errorLog, FILE_APPEND);
        }
    } catch (Exception $e) {
        // SMTP non configuré - continuer sans envoyer d'emails
        $errorLog = sprintf(
            "[%s] EmailService unavailable (SMTP not configured): %s\n",
            $timestamp,
            $e->getMessage()
        );
        file_put_contents($logFile, $errorLog, FILE_APPEND);
    }

    // Réponse de succès
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Rendez-vous enregistré avec succès',
        'data' => [
            'name' => $name,
            'email' => $email,
            'event_type' => $eventType,
            'start_time' => $formattedStart,
            'end_time' => $formattedEnd,
            'config_url' => $configUrl ?: null,
            'timezone' => $timezone
        ]
    ]);
}
// Traitement de l'événement invitee.canceled (rendez-vous annulé)
elseif (isset($event['event']) && $event['event'] === 'invitee.canceled') {
    $payload = $event['payload'] ?? [];
    $name = $payload['name'] ?? 'N/A';
    $email = $payload['email'] ?? 'N/A';
    $eventType = $payload['event_type_name'] ?? 'N/A';

    // Log de l'annulation
    $cancelLog = sprintf(
        "[%s] Appointment CANCELED | Client: %s (%s) | Event: %s\n",
        $timestamp,
        $name,
        $email,
        $eventType
    );
    file_put_contents($logFile, $cancelLog, FILE_APPEND);

    // TODO: Envoyer un email de notification d'annulation au menuisier

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Annulation enregistrée'
    ]);
}
// Autres types d'événements
else {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Event logged successfully'
    ]);
}
?>
