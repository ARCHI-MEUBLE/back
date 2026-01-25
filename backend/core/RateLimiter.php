<?php
/**
 * ArchiMeuble - Rate Limiter
 * Protection contre les attaques par brute force
 *
 * SÉCURITÉ: Limite le nombre de tentatives par IP et par compte
 * Utilise SQLite pour la persistance des données
 *
 * Auteur : Security Fix
 * Date : 2025
 */

require_once __DIR__ . '/Database.php';

class RateLimiter {
    private $db;

    // Configuration par défaut
    private const DEFAULT_MAX_ATTEMPTS = 5;        // Tentatives max
    private const DEFAULT_DECAY_MINUTES = 15;      // Durée du blocage en minutes
    private const DEFAULT_LOCKOUT_MINUTES = 30;    // Durée du lockout après trop de tentatives

    // Multiplicateur pour blocages répétés (backoff exponentiel)
    private const LOCKOUT_MULTIPLIER = 2;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureTableExists();
    }

    /**
     * Crée la table de rate limiting si elle n'existe pas
     */
    private function ensureTableExists(): void {
        $query = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'ip',
            attempts INTEGER DEFAULT 0,
            lockout_count INTEGER DEFAULT 0,
            first_attempt_at DATETIME,
            locked_until DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(identifier, type)
        )";

        try {
            $this->db->execute($query);

            // Index pour les requêtes fréquentes
            $this->db->execute("CREATE INDEX IF NOT EXISTS idx_rate_limits_identifier ON rate_limits(identifier, type)");
            $this->db->execute("CREATE INDEX IF NOT EXISTS idx_rate_limits_locked_until ON rate_limits(locked_until)");
        } catch (Exception $e) {
            error_log("[RateLimiter] Erreur création table: " . $e->getMessage());
        }
    }

    /**
     * Vérifie si une action est autorisée (pas de rate limit dépassé)
     *
     * @param string $identifier - IP ou email/username
     * @param string $type - Type de limitation ('ip', 'account', 'action')
     * @param int $maxAttempts - Nombre max de tentatives
     * @param int $decayMinutes - Fenêtre de temps en minutes
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null]
     */
    public function check(
        string $identifier,
        string $type = 'ip',
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $decayMinutes = self::DEFAULT_DECAY_MINUTES
    ): array {
        // Nettoyer les anciennes entrées
        $this->cleanup();

        // Récupérer l'état actuel
        $record = $this->getRecord($identifier, $type);

        // Si pas d'enregistrement, c'est autorisé
        if (!$record) {
            return [
                'allowed' => true,
                'remaining' => $maxAttempts,
                'retry_after' => null,
                'message' => null
            ];
        }

        // Vérifier si l'utilisateur est en lockout
        if ($record['locked_until']) {
            $lockedUntil = strtotime($record['locked_until']);
            $now = time();

            if ($lockedUntil > $now) {
                $retryAfter = $lockedUntil - $now;
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'retry_after' => $retryAfter,
                    'message' => "Trop de tentatives. Réessayez dans " . ceil($retryAfter / 60) . " minute(s)."
                ];
            }
        }

        // Vérifier si la fenêtre de temps est expirée
        $firstAttempt = strtotime($record['first_attempt_at'] ?? 'now');
        $windowEnd = $firstAttempt + ($decayMinutes * 60);

        if (time() > $windowEnd) {
            // Fenêtre expirée, réinitialiser
            $this->resetAttempts($identifier, $type);
            return [
                'allowed' => true,
                'remaining' => $maxAttempts,
                'retry_after' => null,
                'message' => null
            ];
        }

        // Calculer les tentatives restantes
        $remaining = max(0, $maxAttempts - $record['attempts']);

        return [
            'allowed' => $remaining > 0,
            'remaining' => $remaining,
            'retry_after' => $remaining > 0 ? null : ($windowEnd - time()),
            'message' => $remaining > 0 ? null : "Limite de tentatives atteinte."
        ];
    }

    /**
     * Enregistre une tentative (échouée ou non)
     *
     * @param string $identifier
     * @param string $type
     * @param bool $success - Si la tentative a réussi (reset le compteur)
     * @param int $maxAttempts
     * @param int $lockoutMinutes
     */
    public function hit(
        string $identifier,
        string $type = 'ip',
        bool $success = false,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $lockoutMinutes = self::DEFAULT_LOCKOUT_MINUTES
    ): void {
        if ($success) {
            // Tentative réussie : réinitialiser le compteur
            $this->resetAttempts($identifier, $type);
            return;
        }

        $record = $this->getRecord($identifier, $type);

        if (!$record) {
            // Première tentative
            $query = "INSERT INTO rate_limits (identifier, type, attempts, first_attempt_at, updated_at)
                      VALUES (:identifier, :type, 1, datetime('now'), datetime('now'))";
            $this->db->execute($query, [
                'identifier' => $identifier,
                'type' => $type
            ]);
        } else {
            // Incrémenter le compteur
            $newAttempts = $record['attempts'] + 1;
            $lockoutCount = $record['lockout_count'] ?? 0;

            // Si on dépasse le max, appliquer un lockout
            if ($newAttempts >= $maxAttempts) {
                // Backoff exponentiel : chaque lockout double la durée
                $actualLockout = $lockoutMinutes * pow(self::LOCKOUT_MULTIPLIER, $lockoutCount);
                $actualLockout = min($actualLockout, 1440); // Max 24h

                $query = "UPDATE rate_limits
                          SET attempts = :attempts,
                              lockout_count = :lockout_count,
                              locked_until = datetime('now', '+' || :lockout || ' minutes'),
                              updated_at = datetime('now')
                          WHERE identifier = :identifier AND type = :type";

                $this->db->execute($query, [
                    'attempts' => $newAttempts,
                    'lockout_count' => $lockoutCount + 1,
                    'lockout' => $actualLockout,
                    'identifier' => $identifier,
                    'type' => $type
                ]);

                // Log de sécurité
                error_log("[SECURITY] Rate limit lockout: $type=$identifier, attempts=$newAttempts, lockout={$actualLockout}min");
            } else {
                $query = "UPDATE rate_limits
                          SET attempts = :attempts, updated_at = datetime('now')
                          WHERE identifier = :identifier AND type = :type";

                $this->db->execute($query, [
                    'attempts' => $newAttempts,
                    'identifier' => $identifier,
                    'type' => $type
                ]);
            }
        }
    }

    /**
     * Réinitialise les tentatives pour un identifiant
     */
    public function resetAttempts(string $identifier, string $type = 'ip'): void {
        $query = "UPDATE rate_limits
                  SET attempts = 0,
                      first_attempt_at = NULL,
                      locked_until = NULL,
                      updated_at = datetime('now')
                  WHERE identifier = :identifier AND type = :type";

        $this->db->execute($query, [
            'identifier' => $identifier,
            'type' => $type
        ]);
    }

    /**
     * Récupère l'enregistrement pour un identifiant
     */
    private function getRecord(string $identifier, string $type): ?array {
        $query = "SELECT * FROM rate_limits WHERE identifier = :identifier AND type = :type";
        return $this->db->queryOne($query, [
            'identifier' => $identifier,
            'type' => $type
        ]);
    }

    /**
     * Nettoie les anciennes entrées (plus de 24h sans activité)
     */
    private function cleanup(): void {
        $query = "DELETE FROM rate_limits
                  WHERE updated_at < datetime('now', '-24 hours')
                  AND (locked_until IS NULL OR locked_until < datetime('now'))";

        try {
            $this->db->execute($query);
        } catch (Exception $e) {
            // Ignorer les erreurs de nettoyage
        }
    }

    /**
     * Obtient l'IP réelle du client (gère les proxies)
     */
    public static function getClientIP(): string {
        // Headers à vérifier (dans l'ordre de priorité)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy standard
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_CLIENT_IP',            // Proxy alternatif
            'REMOTE_ADDR'                // Connexion directe
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For peut contenir plusieurs IPs
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                // Valider que c'est une IP valide
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }

                // Accepter aussi les IPs privées (pour dev local)
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Vérifie et enregistre une tentative de login
     * Méthode helper pour simplifier l'utilisation
     *
     * @param string $email - Email de connexion
     * @return array ['allowed' => bool, 'message' => string|null]
     */
    public function checkLogin(string $email): array {
        $ip = self::getClientIP();

        // Double vérification : par IP ET par compte
        $ipCheck = $this->check($ip, 'login_ip', 10, 15);      // 10 tentatives par IP
        $accountCheck = $this->check($email, 'login_account', 5, 30);  // 5 tentatives par compte

        if (!$ipCheck['allowed']) {
            return [
                'allowed' => false,
                'message' => $ipCheck['message'],
                'retry_after' => $ipCheck['retry_after']
            ];
        }

        if (!$accountCheck['allowed']) {
            return [
                'allowed' => false,
                'message' => $accountCheck['message'],
                'retry_after' => $accountCheck['retry_after']
            ];
        }

        return [
            'allowed' => true,
            'remaining_ip' => $ipCheck['remaining'],
            'remaining_account' => $accountCheck['remaining'],
            'message' => null
        ];
    }

    /**
     * Enregistre une tentative de login
     *
     * @param string $email
     * @param bool $success
     */
    public function recordLogin(string $email, bool $success): void {
        $ip = self::getClientIP();

        // Enregistrer pour IP et compte
        $this->hit($ip, 'login_ip', $success, 10, 30);
        $this->hit($email, 'login_account', $success, 5, 60);
    }
}
