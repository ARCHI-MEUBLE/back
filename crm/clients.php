<?php
// Charger le fichier JSON
$file = 'data.json';

// Vérifier si le fichier existe et peut être lu
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
} else {
    $data = ['clients' => []];
}

// Gérer la mise à jour du statut et des notes
if (isset($_POST['update_status'])) {
    $clientIndex = $_POST['client_index'];
    $newStatus = $_POST['status'];
    $newNote = $_POST['note'];
    
    $data['clients'][$clientIndex]['status'] = $newStatus;
    $data['clients'][$clientIndex]['note'] = $newNote;
    
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Gestion de la suppression
if (isset($_GET['delete'])) {
    $indexToDelete = $_GET['delete'];
    array_splice($data['clients'], $indexToDelete, 1);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    header('Location: clients.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients</title>
    <style>
        /* Styles de base */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        h1 {
            text-align: center;
            color: #444;
            margin-top: 20px;
            padding: 0 15px;
        }

        /* Style pour la barre de recherche sticky */
        .search-container {
            position: sticky;
            top: 0;
            background-color: #fff;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .search-input {
            width: 100%;
            max-width: 500px;
            padding: 12px;
            margin: 0 auto;
            display: block;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .search-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        /* Styles pour la table responsive */
        .table-container {
            overflow-x: auto;
            margin: 20px;
            -webkit-overflow-scrolling: touch;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
            background-color: #fff;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .client-row {
            cursor: pointer;
        }

        .client-row:hover {
            background-color: #e9ecef !important;
            transition: background-color 0.2s ease;
        }

        /* Styles des boutons */
        .btn {
            display: inline-block;
            padding: 10px 15px;
            margin: 5px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            text-align: center;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        .btn.delete {
            background-color: #dc3545;
        }

        .btn.delete:hover {
            background-color: #c82333;
        }

        .btn.edit {
            background-color: #28a745;
        }

        .btn.edit:hover {
            background-color: #218838;
        }

        .add-client {
            display: block;
            width: 200px;
            margin: 20px auto;
            text-align: center;
        }

        .container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Styles pour les statuts */
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 5px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .status-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .status-en-cours {
            background-color: #ffd700;
            color: #000;
        }

        .status-a-faire {
            background-color: #87ceeb;
            color: #000;
        }

        .status-annule {
            background-color: #ff6b6b;
            color: white;
        }

        .status-a-appeler {
            background-color: #98fb98;
            color: #000;
        }

        .status-termine {
            background-color: #4CAF50;
            color: white;
        }

        .client-note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
            padding: 5px;
            background-color: #f8f9fa;
            border-radius: 4px;
            word-break: break-word;
        }

        /* Style pour la modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateY(-100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-content h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .modal-content select,
        .modal-content textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .modal-content textarea {
            min-height: 100px;
            resize: vertical;
        }

        .modal-content select:focus,
        .modal-content textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        /* Styles responsives */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .table-container {
                margin: 10px;
            }

            table {
                font-size: 14px;
            }

            th, td {
                padding: 8px 10px;
            }

            td {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 150px;
            }

            .btn {
                padding: 8px 12px;
                font-size: 13px;
                display: block;
                margin: 5px 0;
                width: 100%;
                box-sizing: border-box;
            }

            .status-badge {
                padding: 6px 12px;
                font-size: 12px;
            }

            .modal-content {
                margin: 10% auto;
                padding: 15px;
            }

            .search-input {
                font-size: 14px;
                padding: 10px;
            }

            h1 {
                font-size: 24px;
                margin: 15px 0;
            }
        }

        /* Animation de chargement */
        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
        }

        .loading::after {
            content: "";
            display: block;
            width: 40px;
            height: 40px;
            margin: 0 auto;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="search-container">
        <input type="text" class="search-input" id="searchInput" placeholder="Rechercher un client..." onkeyup="searchClients()">
    </div>

    <h1>Liste des Clients</h1>
    <center><a href="https://configurateur.archimeuble.fr/alexis/01.php" class="status-en-cours">Console</a></center>
    <div class="container">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Numéro</th>
                        <th>Email</th>
                        <th>Adresse Postale</th>
                        <th>État/Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="clientTableBody">
                    <?php foreach ($data['clients'] as $index => $client): ?>
                        <tr class="client-row" 
                            data-client-info="<?php echo strtolower(htmlspecialchars(
                                $client['nom'] . ' ' . 
                                $client['prenom'] . ' ' . 
                                $client['numero'] . ' ' . 
                                $client['adresse_mail'] . ' ' . 
                                $client['adresse_postale'] . ' ' . 
                                (isset($client['status']) ? $client['status'] : '') . ' ' .
                                (isset($client['note']) ? $client['note'] : '')
                            )); ?>"
                            onclick="window.open('projets.php?client=<?php echo $index; ?>', '_blank')">
                            <td><?php echo htmlspecialchars($client['nom']); ?></td>
                            <td><?php echo htmlspecialchars($client['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($client['numero']); ?></td>
                            <td><?php echo htmlspecialchars($client['adresse_mail']); ?></td>
                            <td><?php echo htmlspecialchars($client['adresse_postale']); ?></td>
                            <td>
                                <div onclick="event.stopPropagation();">
                                    <?php
                                    $statusClass = '';
                                    $statusText = isset($client['status']) ? $client['status'] : 'à faire';
                                    switch($statusText) {
                                        case 'en cours':
                                            $statusClass = 'status-en-cours';
                                            break;
                                        case 'à faire':
                                            $statusClass = 'status-a-faire';
                                            break;
                                        case 'annulé':
                                            $statusClass = 'status-annule';
                                            break;
                                        case 'à appeler':
                                            $statusClass = 'status-a-appeler';
                                            break;
                                        case 'terminé':
                                            $statusClass = 'status-termine';
                                            break;
                                    }
                                    ?>
                                    <span 
                                        class="status-badge <?php echo $statusClass; ?>" 
                                        onclick="openStatusModal(<?php echo $index; ?>, '<?php echo htmlspecialchars($statusText, ENT_QUOTES); ?>', '<?php echo htmlspecialchars(isset($client['note']) ? $client['note'] : '', ENT_QUOTES); ?>')"
                                    >
                                        <?php echo htmlspecialchars($statusText); ?>
                                    </span>
                                    <?php if (isset($client['note']) && !empty($client['note'])): ?>
                                        <div class="client-note"><?php echo htmlspecialchars($client['note']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <a href="clients.php?delete=<?php echo $index; ?>" 
                                   class="btn delete" 
                                   onclick="event.stopPropagation(); return confirm('Êtes-vous sûr de vouloir supprimer ce client ?')">Effacer Client</a>
                                <a href="modifier_client.php?client=<?php echo $index; ?>" 
                                   class="btn edit" 
                                   onclick="event.stopPropagation();" 
                                   target="_blank">Modifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="ajouter_client.php" class="btn add-client">Ajouter un Client</a>
        <a href="projets.php" class="btn add-client">Tous les projets</a>
    </div>

    <div id="loading" class="loading"></div>

    <!-- Modal pour modifier le statut et les notes -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h2>Modifier l'état et la note</h2>
            <form method="POST">
                <input type="hidden" name="client_index" id="modalClientIndex">
                <div>
                    <label for="status">État :</label>
                    <select name="status" id="status">
                        <option value="en cours">En cours</option>
                        <option value="à faire">À faire</option>
                        <option value="annulé">Annulé</option>
                        <option value="à appeler">À appeler</option>
                        <option value="terminé">Terminé</option>
                    </select>
                </div>
                <div>
                    <label for="note">Note :</label>
                    <textarea name="note" id="note" rows="4"></textarea>
                </div>
                <div class="modal-buttons">
                    <button type="button" onclick="closeStatusModal()" class="btn delete">Annuler</button>
                    <button type="submit" name="update_status" class="btn">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('statusModal');
        const statusSelect = document.getElementById('status');
        const noteTextarea = document.getElementById('note');
        const loadingElement = document.getElementById('loading');

        function showLoading() {
            loadingElement.style.display = 'block';
        }

        function hideLoading() {
            loadingElement.style.display = 'none';
        }

        function openStatusModal(clientIndex, currentStatus, currentNote) {
            modal.style.display = "block";
            document.getElementById('modalClientIndex').value = clientIndex;
            statusSelect.value = currentStatus;
            noteTextarea.value = currentNote ? currentNote.replace(/\\'/g, "'") : '';
        }

        function closeStatusModal() {
            modal.style.display = "none";
        }

        function searchClients() {
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput.value.toLowerCase().trim();
            const rows = document.getElementsByClassName('client-row');
            let firstMatch = null;

            for (let row of rows) {
                const clientInfo = row.getAttribute('data-client-info');
                const shouldShow = clientInfo.includes(filter);
                row.style.display = shouldShow ? '' : 'none';
                
                if (shouldShow && !firstMatch && filter) {
                    firstMatch = row;
                }
            }

            if (firstMatch) {
                firstMatch.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        }

        // Fermer la modal en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target == modal) {
                closeStatusModal();
            }
        }

        // Amélioration de la recherche avec debounce
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(searchClients, 300);
        });

        // Gestion du défilement fluide pour la recherche avec la touche Entrée
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const visibleRows = Array.from(document.getElementsByClassName('client-row'))
                    .filter(row => row.style.display !== 'none');
                if (visibleRows.length > 0) {
                    visibleRows[0].scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }
            }
        });

        // Gestion des formulaires avec animation de chargement
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                showLoading();
            });
        });

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            hideLoading();
        });
    </script>
</body>
</html>