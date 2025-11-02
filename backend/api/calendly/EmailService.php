<?php
/**
 * Service d'envoi d'emails pour les rendez-vous Calendly
 * Gère les emails de confirmation et de rappel via SMTP
 */

require_once __DIR__ . '/SMTPMailer.php';

class EmailService {
    private $smtpMailer;
    private $adminEmail;

    public function __construct() {
        // Configuration SMTP depuis les variables d'environnement UNIQUEMENT
        $smtpHost = getenv('SMTP_HOST');
        $smtpPort = getenv('SMTP_PORT');
        $smtpUsername = getenv('SMTP_USERNAME');
        $smtpPassword = getenv('SMTP_PASSWORD');
        $fromEmail = getenv('SMTP_FROM_EMAIL');
        $fromName = getenv('SMTP_FROM_NAME');
        $this->adminEmail = getenv('ADMIN_EMAIL');

        // Vérification que toutes les variables sont configurées
        if (!$smtpHost || !$smtpPort || !$smtpUsername || !$smtpPassword || !$fromEmail || !$fromName || !$this->adminEmail) {
            throw new Exception('Configuration SMTP incomplète. Vérifiez les variables d\'environnement dans le fichier .env');
        }

        $this->smtpMailer = new SMTPMailer(
            $smtpHost,
            $smtpPort,
            $smtpUsername,
            $smtpPassword,
            $fromEmail,
            $fromName
        );
    }

    /**
     * Envoie un email de confirmation au client après réservation
     */
    public function sendConfirmationEmail($clientEmail, $clientName, $eventType, $startDateTime, $endDateTime, $configUrl = '') {
        $subject = "Confirmation de votre rendez-vous ArchiMeuble - $eventType";

        $isPhone = stripos($eventType, 'téléphone') !== false || stripos($eventType, 'phone') !== false;
        $contactMethod = $isPhone ? 'téléphone' : 'visioconférence';

        $message = $this->getConfirmationTemplate(
            $clientName,
            $eventType,
            $startDateTime,
            $endDateTime,
            $contactMethod,
            $configUrl
        );

        return $this->sendEmail($clientEmail, $subject, $message);
    }

    /**
     * Envoie un email de rappel 24h avant le rendez-vous
     */
    public function sendReminderEmail($clientEmail, $clientName, $eventType, $startDateTime, $endDateTime, $configUrl = '') {
        $subject = "Rappel : Votre rendez-vous ArchiMeuble demain - $eventType";

        $isPhone = stripos($eventType, 'téléphone') !== false || stripos($eventType, 'phone') !== false;
        $contactMethod = $isPhone ? 'téléphone' : 'visioconférence';

        $message = $this->getReminderTemplate(
            $clientName,
            $eventType,
            $startDateTime,
            $endDateTime,
            $contactMethod,
            $configUrl
        );

        return $this->sendEmail($clientEmail, $subject, $message);
    }

    /**
     * Envoie un email de notification au menuisier
     */
    public function sendAdminNotification($clientName, $clientEmail, $eventType, $startDateTime, $endDateTime, $configUrl = '', $notes = '') {
        $subject = "Nouveau RDV Calendly - ArchiMeuble : $eventType";

        $message = $this->getAdminNotificationTemplate(
            $clientName,
            $clientEmail,
            $eventType,
            $startDateTime,
            $endDateTime,
            $configUrl,
            $notes
        );

        return $this->sendEmail($this->adminEmail, $subject, $message);
    }

    /**
     * Template d'email de confirmation pour le client
     */
    private function getConfirmationTemplate($name, $eventType, $start, $end, $contactMethod, $configUrl) {
        $configSection = '';
        if ($configUrl) {
            $configSection = "
                <div class='info-box'>
                    <p><strong>Votre configuration :</strong></p>
                    <a href='$configUrl' class='button'>Voir ma configuration</a>
                </div>
            ";
        }

        return "
        <html>
        <head>
            <style>
                body { font-family: 'Source Sans 3', Arial, sans-serif; color: #2f2a26; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background-color: #f6f1eb; }
                .header { background-color: #2f2a26; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-family: 'Playfair Display', serif; }
                .content { background-color: white; padding: 40px 30px; margin: 20px; border-radius: 16px; }
                .highlight-box { background-color: #f6f1eb; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2f2a26; }
                .info-box { margin: 20px 0; padding: 15px; background-color: #f9f9f9; border-radius: 8px; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2f2a26; color: white !important; text-decoration: none; border-radius: 24px; margin: 10px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                strong { color: #2f2a26; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✓ Rendez-vous confirmé</h1>
                </div>
                <div class='content'>
                    <p>Bonjour $name,</p>

                    <p>Votre rendez-vous avec ArchiMeuble a bien été enregistré !</p>

                    <div class='highlight-box'>
                        <p><strong>📅 Type de consultation :</strong> $eventType</p>
                        <p><strong>🕐 Date et heure :</strong> $start - $end (heure de Paris)</p>
                        <p><strong>📞 Modalité :</strong> $contactMethod</p>
                    </div>

                    $configSection

                    <div class='info-box'>
                        <p><strong>Ce que nous allons aborder :</strong></p>
                        <ul>
                            <li>Analyse de votre projet de meuble sur mesure</li>
                            <li>Discussion sur vos besoins et contraintes</li>
                            <li>Conseils personnalisés de notre menuisier</li>
                            <li>Estimation budgétaire précise</li>
                        </ul>
                    </div>

                    <p><strong>💡 Avant notre rendez-vous :</strong></p>
                    <ul>
                        <li>Préparez les dimensions de votre espace</li>
                        <li>Notez vos préférences de style et fonctionnalités</li>
                        <li>Si possible, ayez quelques photos de l'emplacement prévu</li>
                    </ul>

                    <p>Vous recevrez un rappel par email 24h avant notre rendez-vous.</p>

                    <p>À très bientôt !<br>
                    L'équipe ArchiMeuble</p>
                </div>
                <div class='footer'>
                    <p>ArchiMeuble - Meubles sur mesure<br>
                    <a href='https://archimeuble.com'>archimeuble.com</a></p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Template d'email de rappel 24h avant
     */
    private function getReminderTemplate($name, $eventType, $start, $end, $contactMethod, $configUrl) {
        $configSection = '';
        if ($configUrl) {
            $configSection = "
                <p>N'oubliez pas de consulter votre configuration avant notre échange :</p>
                <a href='$configUrl' class='button'>Voir ma configuration</a>
            ";
        }

        return "
        <html>
        <head>
            <style>
                body { font-family: 'Source Sans 3', Arial, sans-serif; color: #2f2a26; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background-color: #f6f1eb; }
                .header { background-color: #2f2a26; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-family: 'Playfair Display', serif; }
                .content { background-color: white; padding: 40px 30px; margin: 20px; border-radius: 16px; }
                .highlight-box { background-color: #fff4e6; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ff9800; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2f2a26; color: white !important; text-decoration: none; border-radius: 24px; margin: 10px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                strong { color: #2f2a26; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⏰ Rendez-vous demain !</h1>
                </div>
                <div class='content'>
                    <p>Bonjour $name,</p>

                    <p>Nous avons hâte de vous rencontrer demain pour discuter de votre projet de meuble sur mesure !</p>

                    <div class='highlight-box'>
                        <p><strong>📅 Rappel de votre rendez-vous :</strong></p>
                        <p><strong>Type :</strong> $eventType</p>
                        <p><strong>Date et heure :</strong> $start - $end (heure de Paris)</p>
                        <p><strong>Modalité :</strong> $contactMethod</p>
                    </div>

                    $configSection

                    <p><strong>📝 Checklist pour demain :</strong></p>
                    <ul>
                        <li>✓ Dimensions de l'espace disponible</li>
                        <li>✓ Budget approximatif en tête</li>
                        <li>✓ Liste de vos besoins prioritaires</li>
                        <li>✓ Photos de l'emplacement (si possible)</li>
                    </ul>

                    <p>Si vous avez un empêchement, merci de nous prévenir au plus tôt.</p>

                    <p>À demain !<br>
                    L'équipe ArchiMeuble</p>
                </div>
                <div class='footer'>
                    <p>ArchiMeuble - Meubles sur mesure<br>
                    <a href='https://archimeuble.com'>archimeuble.com</a></p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Template de notification pour l'administrateur
     */
    private function getAdminNotificationTemplate($name, $email, $eventType, $start, $end, $configUrl, $notes) {
        $configSection = '';
        if ($configUrl) {
            $configSection = "
                <div class='info-row'>
                    <span class='label'>Lien de configuration :</span><br>
                    <a href='$configUrl' class='button'>Voir la configuration</a>
                </div>
            ";
        }

        $notesSection = '';
        if ($notes) {
            $notesSection = "
                <div class='info-row'>
                    <span class='label'>Notes supplémentaires :</span><br>
                    $notes
                </div>
            ";
        }

        return "
        <html>
        <head>
            <style>
                body { font-family: 'Source Sans 3', Arial, sans-serif; color: #2f2a26; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f6f1eb; }
                .header { background-color: #2f2a26; color: white; padding: 20px; text-align: center; }
                .content { background-color: white; padding: 30px; margin-top: 20px; border-radius: 8px; }
                .info-row { margin: 15px 0; padding: 10px; background-color: #f6f1eb; border-radius: 4px; }
                .label { font-weight: bold; color: #2f2a26; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2f2a26; color: white; text-decoration: none; border-radius: 24px; margin-top: 10px; }
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
                        <span class='label'>Date et heure :</span> $start - $end
                    </div>

                    $configSection
                    $notesSection

                    <p style='margin-top: 30px;'>✅ Email de confirmation envoyé automatiquement au client.</p>
                    <p>⏰ Un rappel lui sera envoyé 24h avant le rendez-vous.</p>

                    <p>Cordialement,<br>Système ArchiMeuble</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Fonction d'envoi d'email générique via SMTP
     */
    private function sendEmail($to, $subject, $htmlMessage) {
        return $this->smtpMailer->send($to, $subject, $htmlMessage);
    }
}
?>
