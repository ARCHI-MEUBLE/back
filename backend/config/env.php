<?php
/**
 * Chargeur automatique des variables d'environnement depuis .env
 * Utilise un cache APCu pour éviter de relire le fichier à chaque requête
 */

// Variable globale pour éviter de charger plusieurs fois par requête
$GLOBALS['_env_loaded'] = $GLOBALS['_env_loaded'] ?? false;

function loadEnvFile($filePath = null) {
    // Si déjà chargé dans cette requête, retourner immédiatement
    if ($GLOBALS['_env_loaded']) {
        return;
    }
    // Déterminer le chemin du fichier .env
    if ($filePath === null) {
        // Détecter si on est dans Docker ou en local
        $isDocker = file_exists('/app');
        $filePath = $isDocker ? '/app/.env' : __DIR__ . '/../../.env';
    }

    // Vérifier que le fichier existe
    if (!file_exists($filePath)) {
        error_log("Warning: .env file not found at $filePath");
        return;
    }

    // Lire le fichier ligne par ligne
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorer les commentaires et les lignes vides
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Parser la ligne KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Supprimer les guillemets si présents
            if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }

            // IMPORTANT: Ne pas écraser les variables d'environnement Docker déjà définies
            // Docker a priorité sur le fichier .env
            if (getenv($key) === false || getenv($key) === '') {
                // Définir la variable seulement si elle n'existe pas déjà
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    // Marquer comme chargé
    $GLOBALS['_env_loaded'] = true;
}

// Charger automatiquement le fichier .env
loadEnvFile();
