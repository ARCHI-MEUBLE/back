<?php
// Nom du fichier contenant le facteur de prix
$file = 'facteur_prix.txt';

// Vérifier si le fichier existe, sinon en créer un avec une valeur par défaut
if (!file_exists($file)) {
    file_put_contents($file, "1.0"); // Valeur par défaut
}

// Lire la valeur actuelle du facteur de prix
$current_factor = trim(file_get_contents($file));

// Vérifier si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['facteur_prix']) && is_numeric($_POST['facteur_prix'])) {
        $new_factor = trim($_POST['facteur_prix']);
        file_put_contents($file, $new_factor); // Mettre à jour le fichier
        header("Location: 02.php"); // Redirection après mise à jour
        exit();
    } else {
        $message = "Veuillez entrer une valeur numérique valide.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mettre à jour le facteur de prix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 50px;
            text-align: center;
        }
        form {
            display: inline-block;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
            background-color: #f9f9f9;
        }
        input {
            padding: 10px;
            font-size: 16px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message {
            margin-top: 10px;
            color: green;
        }
    </style>
</head>
<body>
    <h2>Mettre à jour le facteur de prix</h2>
    <form method="post">
        <label for="facteur_prix">Facteur de prix :</label>
        <input type="text" id="facteur_prix" name="facteur_prix" value="<?php echo htmlspecialchars($current_factor); ?>" required>
        <br><br>
        <button type="submit">Mettre à jour</button>
    </form>
    
    <?php if (isset($message)) : ?>
        <p class="message"> <?php echo $message; ?> </p>
    <?php endif; ?>
</body>
</html>