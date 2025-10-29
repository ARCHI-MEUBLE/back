<?php
/**
 * ArchiMeuble - Classe Session (Singleton)
 * Gère les sessions PHP
 * Auteur : Ilyes
 * Date : 2025-10-20
 */

class Session {
    private static $instance = null;

    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {
        // Démarrer la session si elle n'est pas déjà démarrée
        if (session_status() === PHP_SESSION_NONE) {
            // Détecter si on est en local ou en production
            $isLocal = (
                isset($_SERVER['HTTP_HOST']) && 
                (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                 strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)
            );

            // Configurer les paramètres de cookie
            session_set_cookie_params([
                'lifetime' => 86400 * 7, // 7 jours
                'path' => '/',
                'domain' => '', // Pas de domaine spécifique
                'secure' => !$isLocal, // HTTPS uniquement en production
                'httponly' => true, // Protection XSS
                'samesite' => $isLocal ? 'Lax' : 'None' // Lax en local, None en prod
            ]);

            session_start();
        }
    }

    /**
     * Empêche le clonage de l'instance
     */
    private function __clone() {}

    /**
     * Empêche la désérialisation de l'instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Retourne l'instance unique de la classe
     * @return Session
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Stocker une valeur dans la session
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Récupérer une valeur de la session
     * @param string $key
     * @param mixed $default Valeur par défaut si la clé n'existe pas
     * @return mixed
     */
    public function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    /**
     * Vérifier si une clé existe dans la session
     * @param string $key
     * @return bool
     */
    public function has($key) {
        return isset($_SESSION[$key]);
    }

    /**
     * Supprimer une valeur de la session
     * @param string $key
     */
    public function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Retourner l'ID de session
     * @return string
     */
    public function getId() {
        return session_id();
    }

    /**
     * Détruire complètement la session
     */
    public function destroy() {
        $_SESSION = [];

        // Détruire le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Récupérer toutes les données de la session
     * @return array
     */
    public function all() {
        return $_SESSION;
    }

    /**
     * Régénérer l'ID de session (sécurité)
     */
    public function regenerate() {
        session_regenerate_id(true);
    }
}
