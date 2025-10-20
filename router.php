<?php
/**
 * ArchiMeuble - Router principal
 * Point d'entrée du serveur - Gère le routing complet
 * Auteur : Ilyes + Collins
 * Date : 2025-10-20
 */

// Inclure les classes nécessaires
require_once __DIR__ . '/backend/core/Session.php';
require_once __DIR__ . '/backend/core/Router.php';

// Démarrer la session
$session = Session::getInstance();

// Créer le router et router la requête
$router = new Router();
$router->route($_SERVER['REQUEST_URI']);
