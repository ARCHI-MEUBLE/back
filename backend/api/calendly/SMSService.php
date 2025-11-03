<?php
/**
 * Service d'envoi de SMS via Twilio pour les rendez-vous téléphoniques
 * Gère les SMS de confirmation et de rappel
 */

class SMSService {
    private $accountSid;
    private $authToken;
    private $fromNumber;

    public function __construct() {
        // Configuration Twilio depuis les variables d'environnement UNIQUEMENT
        $this->accountSid = getenv('TWILIO_ACCOUNT_SID');
        $this->authToken = getenv('TWILIO_AUTH_TOKEN');
        $this->fromNumber = getenv('TWILIO_PHONE_NUMBER');

        // Vérification que toutes les variables sont configurées
        if (!$this->accountSid || !$this->authToken || !$this->fromNumber) {
            throw new Exception('Configuration Twilio incomplète. Vérifiez les variables d\'environnement dans le fichier .env.local');
        }
    }

    /**
     * Envoie un SMS de confirmation au client après réservation téléphonique
     */
    public function sendConfirmationSMS($phoneNumber, $clientName, $eventType, $startDateTime, $endDateTime) {
        $message = "Bonjour $clientName,\n\n";
        $message .= "Votre RDV téléphonique ArchiMeuble est confirmé :\n";
        $message .= "📞 $eventType\n";
        $message .= "📅 $startDateTime - $endDateTime\n\n";
        $message .= "Nous vous appellerons à ce numéro.\n\n";
        $message .= "À bientôt !\n";
        $message .= "ArchiMeuble";

        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Envoie un SMS de rappel 24h avant le rendez-vous téléphonique
     */
    public function sendReminderSMS($phoneNumber, $clientName, $eventType, $startDateTime, $endDateTime) {
        $message = "Rappel RDV ArchiMeuble demain :\n\n";
        $message .= "📞 $eventType\n";
        $message .= "📅 $startDateTime - $endDateTime\n\n";
        $message .= "Nous vous appellerons au $phoneNumber\n\n";
        $message .= "À demain !\n";
        $message .= "ArchiMeuble";

        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Fonction d'envoi de SMS générique via Twilio API
     */
    private function sendSMS($to, $message) {
        // Formater le numéro au format international si nécessaire
        $to = $this->formatPhoneNumber($to);

        // Préparer les données pour l'API Twilio
        $data = [
            'From' => $this->fromNumber,
            'To' => $to,
            'Body' => $message
        ];

        // URL de l'API Twilio
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        // Initialiser cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        // Exécuter la requête
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Vérifier la réponse
        if ($httpCode === 201) {
            error_log("SMS envoyé avec succès à $to");
            return true;
        } else {
            error_log("Erreur envoi SMS à $to : HTTP $httpCode - $response");
            return false;
        }
    }

    /**
     * Formate un numéro de téléphone au format international
     */
    private function formatPhoneNumber($number) {
        // Supprimer les espaces, tirets, points
        $number = preg_replace('/[^0-9+]/', '', $number);

        // Si le numéro commence par 0 (France), remplacer par +33
        if (substr($number, 0, 1) === '0') {
            $number = '+33' . substr($number, 1);
        }

        // Si le numéro ne commence pas par +, ajouter +33 (par défaut France)
        if (substr($number, 0, 1) !== '+') {
            $number = '+33' . $number;
        }

        return $number;
    }
}
?>
