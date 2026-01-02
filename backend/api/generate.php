<?php
/**
 * ArchiMeuble - API Generate
 * Génère un meuble 3D à partir d'un prompt M1
 * Auteur : Ilyes
 * Date : 2025-10-20
 */

// Activer CORS
require_once __DIR__ . '/../core/Cors.php';
Cors::enable();

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Méthode non autorisée. Utilisez POST.'
    ]);
    exit();
}

try {
    // Lire les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Debug : vérifier ce qui est reçu
    if ($data === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'JSON invalide ou vide',
            'debug_raw_input' => $input,
            'debug_content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
        ]);
        exit();
    }

    // Vérifier que le prompt est fourni
    if (!isset($data['prompt']) || empty($data['prompt'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Le paramètre "prompt" est requis',
            'debug_received_data' => $data
        ]);
        exit();
    }

    $prompt = trim($data['prompt']);
    $closed = isset($data['closed']) && $data['closed'] === true;

    // Support pour couleur unique (legacy) ou multi-couleurs
    $colors = null;
    if (isset($data['colors']) && is_array($data['colors'])) {
        // Multi-couleurs : {"structure": "#xxx", "drawers": "#yyy", ...}
        $colors = $data['colors'];
    } elseif (isset($data['color']) && !empty($data['color'])) {
        // Couleur unique (legacy)
        $colors = ['all' => trim($data['color'])];
    }

    // VALIDATION 1 : Regex pour valider le format du prompt
    // Format attendu : M[1-5](largeur,profondeur,hauteur[,modules])MODULES(params)
    // Exemple : M1(1700,500,730)EFH3(F,T,F) ou M1(1400,500,800)EbFSH3(VL[30,70],P,T)
    // Accepte : lettres, chiffres, parenthèses, virgules, crochets
    $promptPattern = '/^M[1-5]\(\d+,\d+,\d+(,\d+)?\)[A-Za-z0-9\(\),\[\]]+$/';

    if (!preg_match($promptPattern, $prompt)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Format de prompt invalide. Attendu : M[1-5](largeur,profondeur,hauteur)MODULES(...)'
        ]);
        exit();
    }

    // VALIDATION 2 : Bloquer les caractères dangereux (injection de commandes)
    $dangerousChars = [';', '&&', '||', '|', '`', '$', '>', '<', "\n", "\r"];
    foreach ($dangerousChars as $char) {
        if (strpos($prompt, $char) !== false) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Caractères interdits dans le prompt'
            ]);
            exit();
        }
    }

    // VALIDATION 3 : Limiter la longueur du prompt
    if (strlen($prompt) > 200) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Prompt trop long (max 200 caractères)'
        ]);
        exit();
    }

    // Générer un nom de fichier unique
    $filename = 'meuble_' . uniqid() . '.glb';

    // Utiliser OUTPUT_DIR si défini, sinon fallback sur /app/models (Docker) ou chemin local
    $outputDir = getenv('OUTPUT_DIR');

    if (!$outputDir || empty($outputDir)) {
        // En production (Docker/Railway), utiliser /app/models
        if (file_exists('/app')) {
            $outputDir = '/app/models';
        } else {
            // En local, utiliser le chemin relatif vers front
            $outputDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'front' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'models';
        }
    }

    $outputDir = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $outputPath = $outputDir . $filename;

    // Créer le dossier si inexistant
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true)) {
            throw new Exception("Impossible de créer le dossier de sortie");
        }
    }

    // Chemin vers le script Python (normaliser les slashes)
    // Utiliser le vrai script de Gauthier
    $pythonScript = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'procedure_real.py';

    // Vérifier que le script Python existe
    if (!file_exists($pythonScript)) {
        throw new Exception("Script Python introuvable : $pythonScript");
    }

    // Construire la commande Python de manière sécurisée
    // Utiliser PYTHON_PATH si défini (Docker), sinon essayer Anaconda puis python par défaut
    $pythonExe = getenv('PYTHON_PATH');

    if (!$pythonExe || !file_exists($pythonExe)) {
        // Essayer Anaconda en local
        $pythonExe = 'F:\\ANACONDA\\python.exe';

        // Si Anaconda n'existe pas, utiliser python par défaut
        if (!file_exists($pythonExe)) {
            $pythonExe = 'python3';
        }
    }

    // Ajouter --closed si demandé
    $closedFlag = $closed ? '--closed' : '';

    // Ajouter --colors si fourni (format JSON)
    $colorsFlag = '';
    if ($colors && !empty($colors)) {
        $colorsJson = json_encode($colors, JSON_UNESCAPED_SLASHES);
        $colorsFlag = '--colors ' . escapeshellarg($colorsJson);
    }

    $command = sprintf(
        '"%s" "%s" %s %s %s %s 2>&1',
        $pythonExe,
        $pythonScript,
        escapeshellarg($prompt),
        escapeshellarg($outputPath),
        $closedFlag,
        $colorsFlag
    );

    // Log de la commande pour debug
    error_log("Commande Python: $command");
    error_log("Output dir: $outputDir");
    error_log("Output path: $outputPath");

    // Mesurer le temps d'exécution
    $startTime = microtime(true);

    // Exécuter la commande Python
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    $executionTime = round(microtime(true) - $startTime, 2);

    // Log de sortie pour debug
    $outputText = implode("\n", $output);
    error_log("Python output: $outputText");
    error_log("Python return code: $returnCode");
    error_log("Python execution time: {$executionTime}s");

    // Vérifier le code de retour
    if ($returnCode !== 0) {
        // Erreur lors de l'exécution Python
        $errorMessage = implode("\n", $output);
        error_log("Erreur Python : $errorMessage");

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erreur lors de la génération du meuble 3D',
            'details' => $errorMessage,
            'debug_command' => $command,
            'debug_output_dir' => $outputDir,
            'debug_python_exe' => $pythonExe
        ]);
        exit();
    }

    // Vérifier que le fichier GLB a été créé
    if (!file_exists($outputPath)) {
        error_log("Fichier GLB non trouvé: $outputPath");
        error_log("Contenu du dossier: " . implode(", ", scandir($outputDir)));

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Le fichier GLB n\'a pas été généré',
            'debug_output_path' => $outputPath,
            'debug_dir_exists' => is_dir($outputDir),
            'debug_dir_writable' => is_writable($outputDir),
            'debug_dir_contents' => scandir($outputDir),
            'debug_python_output' => $outputText
        ]);
        exit();
    }

    // Vérifier si le fichier DXF a été créé
    $dxfFilename = pathinfo($filename, PATHINFO_FILENAME) . '.dxf';
    $dxfPath = $outputDir . $dxfFilename;
    $dxfUrl = null;

    if (file_exists($dxfPath)) {
        $dxfUrl = '/models/' . $dxfFilename;
        error_log("Fichier DXF trouvé: $dxfPath");
    } else {
        error_log("Fichier DXF NON TROUVE après exécution Python: $dxfPath");
        // Essayer de voir si Python a loggé une erreur spécifique
        error_log("Dernières lignes de sortie Python: " . substr($outputText, -500));
    }

    // Succès : retourner l'URL du fichier GLB et DXF
    $glbUrl = '/models/' . $filename;

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'glb_url' => $glbUrl,
        'dxf_url' => $dxfUrl,
        'prompt' => $prompt,
        'filename' => $filename,
        'execution_time' => $executionTime . 's'
    ]);

} catch (Exception $e) {
    error_log("Erreur generate.php : " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}
