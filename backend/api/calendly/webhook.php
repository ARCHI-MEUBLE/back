<?php
/**
 * Webhook Calendly pour ArchiMeuble
 *
 * Ce fichier reçoit les événements Calendly (création de rendez-vous, annulations, etc.)
 * et les traite pour notifier le menuisier et conserver un historique.
 *
 * URL du webhook à configurer dans Calendly :
 * https://votre-domaine.com/api/calendly/webhook.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Calendly-Webhook-Signature');

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

    // Préparation de l'email de notification au menuisier
    $to = 'pro.archimeuble@gmail.com';
    $subject = "Nouveau RDV Calendly - ArchiMeuble : $eventType";

    $message = "
    <html>
    <head>
        <style>
            body { font-family: 'Source Sans 3', Arial, sans-serif; color: #2f2a26; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f6f1eb; }
            .header { background-color: #2f2a26; color: white; padding: 20px; text-align: center; }
            .content { background-color: white; padding: 30px; margin-top: 20px; border-radius: 8px; }
            .info-row { margin: 15px 0; padding: 10px; background-color: #f6f1eb; border-radius: 4px; }
            .label { font-weight: bold; color: #2f2a26; }
            .button { display: inline-block; padding: 12px 24px; background-color: #2f2a26; color: white; text-decoration: none; border-radius: 24px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Nouveau Rendez-vous ArchiMeuble</h1>
            </div>
            <div class='content'>
                <p>Bonjour,</p>
                <p>Un nouveau rendez-vous a été pris sur Calendly :</p>

                <div class='info-row'>
                    <span class='label'>Type de consultation :</span> $eventType
                </div>

                <div class='info-row'>
                    <span class='label'>Client :</span> $name
                </div>

                <div class='info-row'>
                    <span class='label'>Email :</span> <a href='mailto:$email'>$email</a>
                </div>

                <div class='info-row'>
                    <span class='label'>Date et heure :</span> $formattedStart - $formattedEnd
                </div>

                <div class='info-row'>
                    <span class='label'>Fuseau horaire :</span> $timezone
                </div>
    ";

    if ($configUrl) {
        $message .= "
                <div class='info-row'>
                    <span class='label'>Lien de configuration :</span><br>
                    <a href='$configUrl' class='button'>Voir la configuration</a>
                </div>
        ";
    }

    if ($additionalNotes) {
        $message .= "
                <div class='info-row'>
                    <span class='label'>Notes supplémentaires :</span><br>
                    $additionalNotes
                </div>
        ";
    }

    $message .= "
                <p style='margin-top: 30px;'>Pensez à vous préparer pour cet entretien et à vérifier votre agenda.</p>
                <p>Cordialement,<br>Système ArchiMeuble</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // En-têtes pour l'email HTML
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ArchiMeuble <noreply@archimeuble.com>\r\n";
    $headers .= "Reply-To: $email\r\n";

    // Envoi de l'email (décommenter en production)
    // mail($to, $subject, $message, $headers);

    // Log de l'envoi
    $emailLog = sprintf(
        "[%s] Email notification prepared for: %s | Client: %s (%s) | Event: %s\n",
        $timestamp,
        $to,
        $name,
        $email,
        $eventType
    );
    file_put_contents($logFile, $emailLog, FILE_APPEND);

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
