<?php
/**
 * Script CRON pour envoyer les rappels 24h avant les rendez-vous
 *
 * À exécuter toutes les heures via cron :
 * 0 * * * * php /path/to/send_reminders.php >> /path/to/logs/reminders.log 2>&1
 */

require_once __DIR__ . '/EmailService.php';

// Configuration
// Détecter si on est dans Docker ou en local
$isDocker = file_exists('/app');
$dbPath = $isDocker ? '/app/database/archimeuble.db' : __DIR__ . '/../../database/archimeuble.db';
$logFile = $isDocker ? '/app/backend/logs/calendly_reminders.log' : __DIR__ . '/../../logs/calendly_reminders.log';
$timestamp = date('Y-m-d H:i:s');

// Log de démarrage
$startLog = sprintf("[%s] === Starting reminder check ===\n", $timestamp);
file_put_contents($logFile, $startLog, FILE_APPEND);

try {
    // Connexion à la base de données
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Calcul de la fenêtre de temps pour les rappels
    // On envoie les rappels pour les rendez-vous qui ont lieu dans 23h à 25h
    $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
    $reminderStart = clone $now;
    $reminderStart->add(new DateInterval('PT23H')); // Dans 23 heures
    $reminderEnd = clone $now;
    $reminderEnd->add(new DateInterval('PT25H')); // Dans 25 heures

    $startTimeStr = $reminderStart->format('Y-m-d H:i:s');
    $endTimeStr = $reminderEnd->format('Y-m-d H:i:s');

    // Requête pour récupérer les rendez-vous qui nécessitent un rappel
    $stmt = $db->prepare("
        SELECT *
        FROM calendly_appointments
        WHERE status = 'scheduled'
          AND reminder_sent = 0
          AND datetime(start_time) BETWEEN datetime(:start) AND datetime(:end)
    ");

    $stmt->execute([
        ':start' => $startTimeStr,
        ':end' => $endTimeStr
    ]);

    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($appointments);

    $countLog = sprintf("[%s] Found %d appointment(s) requiring reminders\n", $timestamp, $count);
    file_put_contents($logFile, $countLog, FILE_APPEND);

    if ($count === 0) {
        $noAppointmentsLog = sprintf("[%s] No reminders to send\n", $timestamp);
        file_put_contents($logFile, $noAppointmentsLog, FILE_APPEND);
        exit(0);
    }

    // Envoi des rappels
    $emailService = new EmailService();
    $successCount = 0;
    $errorCount = 0;

    foreach ($appointments as $appointment) {
        try {
            // Formatage des dates
            $startDateTime = new DateTime($appointment['start_time']);
            $endDateTime = new DateTime($appointment['end_time']);
            $tz = new DateTimeZone($appointment['timezone']);
            $startDateTime->setTimezone($tz);
            $endDateTime->setTimezone($tz);

            $formattedStart = $startDateTime->format('d/m/Y à H:i');
            $formattedEnd = $endDateTime->format('H:i');

            // Envoi de l'email de rappel
            $sent = $emailService->sendReminderEmail(
                $appointment['client_email'],
                $appointment['client_name'],
                $appointment['event_type'],
                $formattedStart,
                $formattedEnd,
                $appointment['config_url'] ?? ''
            );

            if ($sent) {
                // Marquer le rappel comme envoyé dans la base de données
                $updateStmt = $db->prepare("
                    UPDATE calendly_appointments
                    SET reminder_sent = 1, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $updateStmt->execute([':id' => $appointment['id']]);

                $successCount++;
                $successLog = sprintf(
                    "[%s] Reminder sent successfully to %s (%s) for %s\n",
                    $timestamp,
                    $appointment['client_name'],
                    $appointment['client_email'],
                    $formattedStart
                );
                file_put_contents($logFile, $successLog, FILE_APPEND);
            } else {
                $errorCount++;
                $errorLog = sprintf(
                    "[%s] Failed to send reminder to %s (%s)\n",
                    $timestamp,
                    $appointment['client_name'],
                    $appointment['client_email']
                );
                file_put_contents($logFile, $errorLog, FILE_APPEND);
            }

        } catch (Exception $e) {
            $errorCount++;
            $errorLog = sprintf(
                "[%s] Error processing appointment ID %d: %s\n",
                $timestamp,
                $appointment['id'],
                $e->getMessage()
            );
            file_put_contents($logFile, $errorLog, FILE_APPEND);
        }
    }

    // Résumé final
    $summaryLog = sprintf(
        "[%s] === Reminder check completed: %d sent, %d errors ===\n",
        $timestamp,
        $successCount,
        $errorCount
    );
    file_put_contents($logFile, $summaryLog, FILE_APPEND);

} catch (PDOException $e) {
    $errorLog = sprintf(
        "[%s] Database Error: %s\n",
        $timestamp,
        $e->getMessage()
    );
    file_put_contents($logFile, $errorLog, FILE_APPEND);
    exit(1);
} catch (Exception $e) {
    $errorLog = sprintf(
        "[%s] General Error: %s\n",
        $timestamp,
        $e->getMessage()
    );
    file_put_contents($logFile, $errorLog, FILE_APPEND);
    exit(1);
}

exit(0);
?>
