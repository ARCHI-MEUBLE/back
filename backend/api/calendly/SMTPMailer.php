<?php
/**
 * Simple SMTP Mailer sans dépendances
 * Utilisé pour envoyer des emails via Gmail SMTP
 */
class SMTPMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $socket;

    public function __construct($host, $port, $username, $password, $fromEmail, $fromName) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    /**
     * Envoie un email via SMTP
     */
    public function send($to, $subject, $htmlBody) {
        try {
            // Connexion au serveur SMTP
            $this->connect();
            $this->authenticate();

            // Construction et envoi de l'email
            $this->sendCommand("MAIL FROM: <{$this->fromEmail}>");
            $this->sendCommand("RCPT TO: <$to>");
            $this->sendCommand("DATA");

            // Headers
            $headers = [
                "From: {$this->fromName} <{$this->fromEmail}>",
                "To: $to",
                "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "Content-Transfer-Encoding: 8bit"
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
            fwrite($this->socket, $message . "\r\n");
            $this->getResponse();

            $this->sendCommand("QUIT");
            fclose($this->socket);

            return true;
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Connexion au serveur SMTP
     */
    private function connect() {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $this->socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            5, // Reduced from 30s to 5s to prevent frontend timeouts
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
        }

        $this->getResponse();

        // Initier STARTTLS si port 587
        if ($this->port == 587) {
            $this->sendCommand("EHLO {$this->host}");
            $this->sendCommand("STARTTLS");

            stream_socket_enable_crypto(
                $this->socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            $this->sendCommand("EHLO {$this->host}");
        } else {
            $this->sendCommand("HELO {$this->host}");
        }
    }

    /**
     * Authentification SMTP
     */
    private function authenticate() {
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
    }

    /**
     * Envoie une commande SMTP
     */
    private function sendCommand($command) {
        fwrite($this->socket, $command . "\r\n");
        return $this->getResponse();
    }

    /**
     * Récupère la réponse du serveur
     */
    private function getResponse() {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }
}
?>
