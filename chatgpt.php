<?php
// Augmentation de la limite de temps d'exécution
set_time_limit(60);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $question = $_POST["question"];
    
    $api_key = "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";
    $url = "https://api.openai.com/v1/chat/completions";
    
    $code_python = file_get_contents("code_python.txt"); // Charge le code depuis un fichier texte
    $notice = file_get_contents("notice.txt"); // Charge le code depuis un fichier texte
    $example = file_get_contents("example.txt"); // Charge le code depuis un fichier texte

    $data = [
        "model" => "gpt-4",
        "messages" => [
            ["role" => "system", "content" => "Tu es un assistant spécialisé dans la génération de code pour des meubles. Tu dois UNIQUEMENT répondre avec le code du meuble, sans explication, sans formatage markdown, sans préambule ni conclusion. Juste le code brut. Voici le code Python que tu connais :\n\n" . $code_python],
            ["role" => "system", "content" => "Voici la notice d'utilisation :\n\n" . $notice],
            ["role" => "system", "content" => "Voici quelques exemples de code meuble :\n\n" . $example],
            ["role" => "system", "content" => "IMPORTANT: Ne réponds qu'avec le code du meuble, sans aucun texte supplémentaire, sans formatage, sans guillemets, sans aucune explication. Le code doit être directement utilisable."],
            ["role" => "user", "content" => "Génère le code pour : " . $question]
        ]
    ];
    
    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\n" .
                      "Authorization: Bearer $api_key\r\n",
            "method" => "POST",
            "content" => json_encode($data),
            "timeout" => 30.0
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result, true);
    $answer = $response["choices"][0]["message"]["content"] ?? "Erreur de réponse.";
    
    // Nettoyage du code (suppression des backticks, etc.)
    $answer = preg_replace('/```(?:python)?\s*(.*?)\s*```/s', '$1', $answer);
    $answer = trim($answer);
    
    // Si le code commence par "code = ", on le supprime
    $answer = preg_replace('/^code\s*=\s*["\']/', '', $answer);
    $answer = preg_replace('/["\']$/', '', $answer);
    
    // Autre nettoyage potentiel
    $answer = str_replace(["Python", "python", "Code:", "code:"], "", $answer);
    $answer = trim($answer);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArchiSmart - Concepteur de Meubles</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            background-color: #024F82;
            color: white;
            padding: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        #chat-container {
            max-width: 800px;
            margin: 0 auto 50px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .input-group {
            display: flex;
            margin-bottom: 25px;
        }
        
        input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #007BFF;
        }
        
        button {
            padding: 12px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #218838;
        }
        
        #response {
            margin: 25px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            text-align: left;
            font-family: monospace;
            font-size: 16px;
            white-space: pre-wrap;
            border-left: 4px solid #28a745;
        }
        
        .button-group {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-primary {
            background-color: #007BFF;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        
        .instructions {
            margin-top: 30px;
            text-align: left;
            padding: 20px;
            background-color: #f8f9fa;
            border-left: 4px solid #007BFF;
            color: #333;
            font-size: 14px;
            border-radius: 0 4px 4px 0;
        }
        
        .loading {
            display: none;
            margin: 20px auto;
            text-align: center;
        }
        
        .loading-spinner {
            border: 6px solid #f3f3f3;
            border-top: 6px solid #28a745;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        h1, h2 {
            margin: 0;
            padding: 0;
        }
        
        h2 {
            margin-bottom: 20px;
            color: #024F82;
        }
        
        ul {
            text-align: left;
            padding-left: 20px;
        }
        
        li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ArchiSmart - Concepteur de Meubles</h1>
    </div>
    
    <div id="chat-container">
        <h2>Décrivez le meuble que vous souhaitez créer</h2>
        
        <form method="POST" id="codeForm">
            <div class="input-group">
                <input type="text" name="question" id="questionInput" placeholder="Ex: Une bibliothèque de 2m de haut avec 4 étagères..." required value="<?php echo isset($question) ? htmlspecialchars($question) : ''; ?>">
                <button type="submit" id="submitBtn">Générer</button>
            </div>
        </form>
        
        <div id="loading" class="loading">
            <div class="loading-spinner"></div>
            <p>Génération du code en cours... Merci de patienter.</p>
        </div>
        
        <?php if (isset($answer) && $answer != "Erreur de réponse.") { ?>
            <div id="response"><?php echo htmlspecialchars($answer); ?></div>
            
            <div class="button-group">
                
                <form id="view02Form" action="02.php" method="POST">
                    <input type="hidden" id="inputString02" name="inputString" value="<?php echo htmlspecialchars($answer); ?>">
                    <button type="submit" class="btn btn-success">Visualiser en 3D</button>
                </form>
            </div>
            
            <script>
                // Sauvegarde du code dans l'historique local
                document.addEventListener('DOMContentLoaded', function() {
                    const chain = "<?php echo addslashes($answer); ?>";
                    if (chain) {
                        let history = JSON.parse(localStorage.getItem('chainHistory') || '[]');
                        if (!history.includes(chain)) {
                            history.unshift(chain);
                            history = history.slice(0, 5);
                            localStorage.setItem('chainHistory', JSON.stringify(history));
                        }
                    }
                });
            </script>
        <?php } elseif (isset($answer) && $answer == "Erreur de réponse.") { ?>
            <div id="response" style="color: red; border-left-color: #dc3545;">
                Désolé, une erreur est survenue lors de la génération du code. Veuillez réessayer ou reformuler votre demande.
            </div>
        <?php } ?>
        
        <div class="instructions">
            <h3>Comment décrire votre meuble</h3>
            <p>Pour obtenir le meilleur résultat, soyez précis dans votre description. Exemples :</p>
            <ul>
                <li>"Une commode de 120cm de large avec 3 tiroirs"</li>
                <li>"Une bibliothèque de 2m de haut, 1m de large avec 5 étagères"</li>
                <li>"Un bureau d'angle avec caisson à droite"</li>
                <li>"Une armoire de cuisine haute avec 2 portes et 3 tiroirs en bas"</li>
                <li>"Un meuble TV de 180cm avec niche centrale et rangements"</li>
            </ul>
            <p>Vous pouvez spécifier les dimensions, le nombre de compartiments, tiroirs, portes, et les caractéristiques particulières que vous souhaitez.</p>
        </div>
    </div>
    
    <script>
        // Afficher le spinner de chargement lors de la soumission du formulaire
        document.getElementById('codeForm').addEventListener('submit', function() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('submitBtn').disabled = true;
        });
    </script>
</body>
</html>