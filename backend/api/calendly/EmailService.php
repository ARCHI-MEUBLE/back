<?php
/**
 * Service d'envoi d'emails pour les rendez-vous Calendly
 * Gère les emails de confirmation et de rappel via Resend API
 */

class EmailService {
    private $adminEmail;

    public function __construct() {
        // Configuration Resend API - plus simple et plus fiable que SMTP
        $this->adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@archimeuble.com';
    }

    /**
     * Envoie un email de confirmation au client après réservation
     */
    public function sendConfirmationEmail($clientEmail, $clientName, $eventType, $startDateTime, $endDateTime, $configUrl = '', $meetingUrl = null) {
        $subject = "Confirmation de votre rendez-vous ArchiMeuble - $eventType";

        $isPhone = stripos($eventType, 'téléphone') !== false || stripos($eventType, 'phone') !== false;
        $contactMethod = $isPhone ? 'téléphone' : 'visioconférence';

        $message = $this->getConfirmationTemplate(
            $clientName,
            $eventType,
            $startDateTime,
            $endDateTime,
            $contactMethod,
            $configUrl,
            $meetingUrl
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
    private function getConfirmationTemplate($name, $eventType, $start, $end, $contactMethod, $configUrl, $meetingUrl = null) {
        $configSection = '';
        if ($configUrl) {
            $configSection = "
                <div class='info-box'>
                    <p><strong>Votre configuration :</strong></p>
                    <a href='$configUrl' class='button'>Voir ma configuration</a>
                </div>
            ";
        }

        $meetingSection = '';
        if ($meetingUrl && $contactMethod === 'visioconférence') {
            $meetingSection = "
                <div class='info-box' style='background-color: #e8f5e9; border-left: 4px solid #4caf50;'>
                    <p><strong>🎥 Lien de visioconférence :</strong></p>
                    <a href='$meetingUrl' class='button' style='background-color: #4caf50;'>Rejoindre la visioconférence</a>
                    <p style='font-size: 12px; color: #666; margin-top: 10px;'>Cliquez sur ce lien au moment du rendez-vous pour nous rejoindre.</p>
                </div>
            ";
        }

        // Logo en base64
        $logoPath = __DIR__ . '/assets/logo.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
        }

        return "
        <html>
        <head>
            <style>
                body { font-family: 'Source Sans 3', Arial, sans-serif; color: #2f2a26; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background-color: #f6f1eb; }
                .header { background-color: #2f2a26; color: white; padding: 30px 20px; text-align: center; }
                .header img { max-width: 200px; height: auto; margin-bottom: 15px; }
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
                    " . ($logoBase64 ? "<img src='$logoBase64' alt='ArchiMeuble' />" : "") . "
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

                    $meetingSection

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

        // Logo en base64
        $logoPath = __DIR__ . '/assets/logo.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
        }

        return "
        <html>
        <head>
            <style>
                body { font-family: 'Source Sans 3', Arial, sans-serif; color: #2f2a26; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background-color: #f6f1eb; }
                .header { background-color: #2f2a26; color: white; padding: 30px 20px; text-align: center; }
                .header img { max-width: 200px; height: auto; margin-bottom: 15px; }
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
                    " . ($logoBase64 ? "<img src='$logoBase64' alt='ArchiMeuble' />" : "") . "
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

        // Logo en base64
        $logoPath = __DIR__ . '/assets/logo.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
        }

        return "
        <html>
        <head>
            <style>
                body { font-family: 'Source Sans 3', Arial, sans-serif; color: #2f2a26; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f6f1eb; }
                .header { background-color: #2f2a26; color: white; padding: 20px; text-align: center; }
                .header img { max-width: 200px; height: auto; margin-bottom: 15px; }
                .content { background-color: white; padding: 30px; margin-top: 20px; border-radius: 8px; }
                .info-row { margin: 15px 0; padding: 10px; background-color: #f6f1eb; border-radius: 4px; }
                .label { font-weight: bold; color: #2f2a26; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2f2a26; color: white; text-decoration: none; border-radius: 24px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    " . ($logoBase64 ? "<img src='$logoBase64' alt='ArchiMeuble' />" : "") . "
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
     * Fonction d'envoi d'email générique via Resend API (remplace SMTP)
     */
    /**
     * Envoie un email via Resend API (remplace SMTP)
     */
    private function sendEmail($to, $subject, $htmlMessage) {
        $apiKey = getenv('RESEND_API_KEY');
        $from = 'contact@archimeuble.com'; // Domaine vérifié sur Resend

        if (!$apiKey) {
            error_log("Calendly EmailService ERROR: RESEND_API_KEY not configured");
            return false;
        }

        error_log("Calendly EmailService: Sending email to {$to} via Resend API");

        $data = [
            'from' => $from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $htmlMessage,
        ];

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Calendly EmailService: Email sent successfully to {$to}");
            return true;
        } else {
            error_log("Calendly EmailService ERROR: Resend API failed with code {$httpCode}. Response: {$response}. Curl Error: {$error}");
            return false;
        }
    }
}
?>
