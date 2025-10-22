<?php
/**
 * ArchiMeuble - Router principal
 * Point d'entrée du serveur - Gère le routing complet
 * Auteur : Ilyes + Collins
 * Date : 2025-10-20
 */

// Afficher les erreurs dans la sortie standard
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

// Configurer les paramètres de session AVANT de démarrer la session
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');

// Inclure les classes nécessaires
require_once __DIR__ . '/backend/core/Session.php';
require_once __DIR__ . '/backend/core/Router.php';

// Démarrer la session
$session = Session::getInstance();

// Créer le router et router la requête
$router = new Router();
$router->route($_SERVER['REQUEST_URI']);
