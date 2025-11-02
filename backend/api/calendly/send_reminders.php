<?php
/**
 * Script CRON pour envoyer les rappels avant les rendez-vous
 *
 * Envoie 2 types de rappels :
 * - Rappel 24h avant (fenêtre: 23h-25h avant le RDV)
 * - Rappel 1h avant (fenêtre: 50min-70min avant le RDV)
 *
 * À exécuter toutes les 15 minutes via cron :
 * Example cron: every 15 minutes - 0,15,30,45 * * * * php /path/to/send_reminders.php
 */

require_once __DIR__ . '/EmailService.php';

// Configuration
$isDocker = file_exists('/app');
$dbPath = $isDocker ? '/app/database/archimeuble.db' : __DIR__ . '/../../database/archimeuble.db';
$logFile = $isDocker ? '/app/logs/calendly_reminders.log' : __DIR__ . '/../../logs/calendly_reminders.log';
$logDir = dirname($logFile);

// Créer le dossier de logs si nécessaire
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$timestamp = date('Y-m-d H:i:s');
$startLog = sprintf("[%s] === Starting reminder check ===\n", $timestamp);
file_put_contents($logFile, $startLog, FILE_APPEND);

try {
    // Connexion à la base de données
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vérifier si les colonnes reminder_24h_sent et reminder_1h_sent existent
    $tableInfo = $db->query("PRAGMA table_info(calendly_appointments)")->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($tableInfo, 'name');

    $has24hColumn = in_array('reminder_24h_sent', $columns);
    $has1hColumn = in_array('reminder_1h_sent', $columns);
    $hasOldColumn = in_array('reminder_sent', $columns);

    // Ajouter les colonnes si elles n'existent pas
    if (!$has24hColumn && $hasOldColumn) {
        // Renommer l'ancienne colonne n'est pas possible en SQLite, donc on ajoute la nouvelle
        $db->exec("ALTER TABLE calendly_appointments ADD COLUMN reminder_24h_sent BOOLEAN DEFAULT 0");
        // Copier les valeurs de reminder_sent vers reminder_24h_sent
        $db->exec("UPDATE calendly_appointments SET reminder_24h_sent = reminder_sent WHERE reminder_sent = 1");
        $has24hColumn = true;
    } elseif (!$has24hColumn) {
        $db->exec("ALTER TABLE calendly_appointments ADD COLUMN reminder_24h_sent BOOLEAN DEFAULT 0");
        $has24hColumn = true;
    }

    if (!$has1hColumn) {
        $db->exec("ALTER TABLE calendly_appointments ADD COLUMN reminder_1h_sent BOOLEAN DEFAULT 0");
        $has1hColumn = true;
    }

    $emailService = new EmailService();
    $now = new DateTime('now', new DateTimeZone('Europe/Paris'));

    // ========== RAPPELS 24H ==========
    $reminder24hStart = clone $now;
    $reminder24hStart->add(new DateInterval('PT23H'));
    $reminder24hEnd = clone $now;
    $reminder24hEnd->add(new DateInterval('PT25H'));

    $stmt24h = $db->prepare("
        SELECT *
        FROM calendly_appointments
        WHERE status = 'scheduled'
          AND reminder_24h_sent = 0
          AND datetime(start_time) BETWEEN datetime(:start) AND datetime(:end)
    ");

    $stmt24h->execute([
        ':start' => $reminder24hStart->format('Y-m-d H:i:s'),
        ':end' => $reminder24hEnd->format('Y-m-d H:i:s')
    ]);

    $appointments24h = $stmt24h->fetchAll(PDO::FETCH_ASSOC);
    $count24h = count($appointments24h);

    $log24h = sprintf("[%s] Found %d appointment(s) requiring 24h reminder\n", $timestamp, $count24h);
    file_put_contents($logFile, $log24h, FILE_APPEND);

    $success24h = 0;
    foreach ($appointments24h as $appointment) {
        try {
            $startDT = new DateTime($appointment['start_time']);
            $endDT = new DateTime($appointment['end_time']);
            $tz = new DateTimeZone($appointment['timezone']);
            $startDT->setTimezone($tz);
            $endDT->setTimezone($tz);

            $sent = $emailService->sendReminderEmail(
                $appointment['client_email'],
                $appointment['client_name'],
                $appointment['event_type'],
                $startDT->format('d/m/Y à H:i'),
                $endDT->format('H:i'),
                $appointment['config_url'] ?? ''
            );

            if ($sent) {
                $db->prepare("UPDATE calendly_appointments SET reminder_24h_sent = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
                   ->execute([':id' => $appointment['id']]);
                $success24h++;
                $log = sprintf("[%s] 24h reminder sent to %s (%s)\n", $timestamp, $appointment['client_name'], $appointment['client_email']);
                file_put_contents($logFile, $log, FILE_APPEND);
            }
        } catch (Exception $e) {
            $errorLog = sprintf("[%s] Error sending 24h reminder to %s: %s\n", $timestamp, $appointment['client_email'], $e->getMessage());
            file_put_contents($logFile, $errorLog, FILE_APPEND);
        }
    }

    // ========== RAPPELS 1H ==========
    $reminder1hStart = clone $now;
    $reminder1hStart->add(new DateInterval('PT50M')); // 50 minutes
    $reminder1hEnd = clone $now;
    $reminder1hEnd->add(new DateInterval('PT70M')); // 70 minutes

    $stmt1h = $db->prepare("
        SELECT *
        FROM calendly_appointments
        WHERE status = 'scheduled'
          AND reminder_1h_sent = 0
          AND datetime(start_time) BETWEEN datetime(:start) AND datetime(:end)
    ");

    $stmt1h->execute([
        ':start' => $reminder1hStart->format('Y-m-d H:i:s'),
        ':end' => $reminder1hEnd->format('Y-m-d H:i:s')
    ]);

    $appointments1h = $stmt1h->fetchAll(PDO::FETCH_ASSOC);
    $count1h = count($appointments1h);

    $log1h = sprintf("[%s] Found %d appointment(s) requiring 1h reminder\n", $timestamp, $count1h);
    file_put_contents($logFile, $log1h, FILE_APPEND);

    $success1h = 0;
    foreach ($appointments1h as $appointment) {
        try {
            $startDT = new DateTime($appointment['start_time']);
            $endDT = new DateTime($appointment['end_time']);
            $tz = new DateTimeZone($appointment['timezone']);
            $startDT->setTimezone($tz);
            $endDT->setTimezone($tz);

            $sent = $emailService->sendReminderEmail(
                $appointment['client_email'],
                $appointment['client_name'],
                $appointment['event_type'],
                $startDT->format('d/m/Y à H:i'),
                $endDT->format('H:i'),
                $appointment['config_url'] ?? ''
            );

            if ($sent) {
                $db->prepare("UPDATE calendly_appointments SET reminder_1h_sent = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
                   ->execute([':id' => $appointment['id']]);
                $success1h++;
                $log = sprintf("[%s] 1h reminder sent to %s (%s)\n", $timestamp, $appointment['client_name'], $appointment['client_email']);
                file_put_contents($logFile, $log, FILE_APPEND);
            }
        } catch (Exception $e) {
            $errorLog = sprintf("[%s] Error sending 1h reminder to %s: %s\n", $timestamp, $appointment['client_email'], $e->getMessage());
            file_put_contents($logFile, $errorLog, FILE_APPEND);
        }
    }

    // Résumé
    $summary = sprintf(
        "[%s] === Completed: %d/% 24h reminders, %d/%d 1h reminders ===\n",
        $timestamp,
        $success24h,
        $count24h,
        $success1h,
        $count1h
    );
    file_put_contents($logFile, $summary, FILE_APPEND);

} catch (Exception $e) {
    $errorLog = sprintf("[%s] Fatal Error: %s\n", $timestamp, $e->getMessage());
    file_put_contents($logFile, $errorLog, FILE_APPEND);
    exit(1);
}

exit(0);
?>
