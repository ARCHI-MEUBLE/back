<?php
// Charger le fichier JSON
$file = 'data.json';

// Vérifier si le fichier existe et peut être lu
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true); // Convertir le JSON en tableau associatif
} else {
    // Si le fichier n'existe pas, initialiser un tableau vide
    $data = ['clients' => []];
}

// Vérifier si un client a été spécifié dans l'URL
if (isset($_GET['client']) && isset($data['clients'][$_GET['client']])) {
    $clientIndex = $_GET['client'];
    $client = $data['clients'][$clientIndex]; // Récupérer les données du client
    $displayClientProjects = true; // Variable pour indiquer qu'on affiche les projets d'un client spécifique
} else {
    // Si aucun client n'est spécifié ou si l'index est invalide, afficher tous les projets
    $displayClientProjects = false; // Afficher tous les projets des clients
}

// Vérifier si un projet doit être supprimé
if (isset($_GET['delete_project'])) {
    $projectIndex = $_GET['delete_project'];
    
    // Vérifier que l'index du projet existe pour le client
    if (isset($data['clients'][$clientIndex]['projets'][$projectIndex])) {
        // Supprimer le projet du client
        array_splice($data['clients'][$clientIndex]['projets'], $projectIndex, 1);
        
        // Sauvegarder les données mises à jour dans le fichier JSON
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        
        // Rediriger vers la page des projets du client après la suppression
        header('Location: projets.php?client=' . $clientIndex);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projets</title>
    <style>
        /* Reset et styles de base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 95%;
            max-width: 1400px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* En-tête et titres */
        h1 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            color: #2c3e50;
            margin-bottom: 2rem;
            text-align: center;
        }

        h3 {
            font-size: clamp(1.4rem, 3vw, 1.8rem);
            color: #34495e;
            margin: 1.5rem 0;
            border-left: 5px solid #3498db;
            padding-left: 1rem;
        }

        /* Informations client */
        .client-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .client-info p {
            margin: 0.5rem 0;
            font-size: 1.1rem;
        }

        /* Grille de projets */
        .project-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        /* Carte de projet */
        .project {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .project:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .project-header {
            padding: 1.5rem;
            background: #f8f9fa;
        }

        .project-header h4 {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        /* Galerie d'images */
        .image-gallery {
            position: relative;
            height: 250px;
            overflow: hidden;
        }

        .gallery-container {
            display: flex;
            transition: transform 0.5s ease;
        }

        .gallery-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .gallery-nav {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 0.5rem;
        }

        .gallery-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
        }

        .gallery-dot.active {
            background: white;
        }

        /* Contenu du projet */
        .project-content {
            padding: 1.5rem;
        }

        .project-description {
            margin-bottom: 1rem;
            color: #666;
        }

        /* Boutons */
        .button-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s, transform 0.2s;
            flex: 1;
            min-width: 120px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-edit {
            background: #2ecc71;
            color: white;
        }

        .btn-edit:hover {
            background: #27ae60;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .main-btn {
            display: block;
            width: 100%;
            padding: 1rem;
            margin: 1rem 0;
            text-align: center;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .main-btn:hover {
            background: #2980b9;
        }

        /* Responsivité */
        @media (max-width: 768px) {
            .container {
                width: 100%;
                padding: 1rem;
                margin: 0;
                border-radius: 0;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .project-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .project-header h4 {
                font-size: 1.1rem;
            }

            .image-gallery {
                height: 200px;
            }

            .gallery-image {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Projets</h1>

        <?php if ($displayClientProjects): ?>
            <div class="client-info">
                <h3>Détails du Client</h3>
                <p><strong>Nom :</strong> <?php echo htmlspecialchars($client['nom']); ?></p>
                <p><strong>Prénom :</strong> <?php echo htmlspecialchars($client['prenom']); ?></p>
                <p><strong>Numéro :</strong> <?php echo htmlspecialchars($client['numero']); ?></p>
                <p><strong>Email :</strong> <?php echo htmlspecialchars($client['adresse_mail']); ?></p>
                <p><strong>Adresse Postale :</strong> <?php echo htmlspecialchars($client['adresse_postale']); ?></p>
            </div>

            <h3>Projets</h3>
            <div class="project-container">
                <?php if (!empty($client['projets'])): ?>
                    <?php foreach ($client['projets'] as $index => $projet): ?>
                        <div class="project">
                            <div class="project-header">
                                <h4><?php echo htmlspecialchars($projet['titre']); ?></h4>
                            </div>
                            
                            <div class="image-gallery">
                                <div class="gallery-container">
                                    <?php 
                                    $images = is_array($projet['images']) ? $projet['images'] : [$projet['chemin_image']];
                                    foreach ($images as $img): 
                                    ?>
                                        <img class="gallery-image" src="<?php echo htmlspecialchars($img); ?>" alt="Image du projet">
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($images) > 1): ?>
                                    <div class="gallery-nav">
                                        <?php foreach ($images as $index => $img): ?>
                                            <div class="gallery-dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                 data-index="<?php echo $index; ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="project-content">
                                <div class="project-description">
                                    <p><strong>Description :</strong> <?php echo htmlspecialchars($projet['description']); ?></p>
                                    <p><strong>Code Meuble :</strong> <?php echo htmlspecialchars($projet['code_meuble']); ?></p>
                                </div>

                                <div class="button-group">
                                    <form action="./../02.php" method="POST" style="flex: 1;">
                                        <input type="hidden" name="nom" value="<?php echo htmlspecialchars($client['nom']); ?>">
                                        <input type="hidden" name="prenom" value="<?php echo htmlspecialchars($client['prenom']); ?>">
                                        <input type="hidden" name="adresse" value="<?php echo htmlspecialchars($client['adresse_postale']); ?>">
                                        <input type="hidden" name="mail" value="<?php echo htmlspecialchars($client['adresse_mail']); ?>">
                                        <input type="hidden" name="telephone" value="<?php echo htmlspecialchars($client['numero']); ?>">
                                        <input type="hidden" name="titre_projet" value="<?php echo htmlspecialchars($projet['titre']); ?>">
                                        <input type="hidden" name="description_projet" value="<?php echo htmlspecialchars($projet['description']); ?>">
                                        <input type="hidden" name="inputString" value="<?php echo htmlspecialchars($projet['code_meuble']); ?>">
                                        <input type="hidden" name="images" value="<?php echo htmlspecialchars(json_encode($images)); ?>">
                                        <button type="submit" class="btn btn-primary">Aller au meuble</button>
                                    </form>

                                    <a href="modifier_projet.php?client=<?php echo $clientIndex; ?>&project=<?php echo $index; ?>" 
                                       class="btn btn-edit">Modifier</a>
                                    <a href="projets.php?client=<?php echo $clientIndex; ?>&delete_project=<?php echo $index; ?>" 
                                       class="btn btn-delete" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce projet ?')">Supprimer</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucun projet trouvé pour ce client.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Code similaire pour l'affichage de tous les projets -->
        <?php endif; ?>

        <a href="clients.php" class="main-btn">Retour à la liste des clients</a>
        <a href="ajouter_projet.php?client=<?php echo $clientIndex; ?>" class="main-btn">Nouveau Projet</a>
 <a href="https://configurateur.archimeuble.fr/alexis/01.php" class="main-btn">Console</a>
    </div>


    <script>
        // Script pour la galerie d'images
        document.querySelectorAll('.image-gallery').forEach(gallery => {
            const container = gallery.querySelector('.gallery-container');
            const dots = gallery.querySelectorAll('.gallery-dot');
            const imageWidth = gallery.offsetWidth;

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    // Mise à jour de la position
                    container.style.transform = `translateX(-${index * imageWidth}px)`;
                    
                    // Mise à jour des dots actifs
                    dots.forEach(d => d.classList.remove('active'));
                    dot.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>