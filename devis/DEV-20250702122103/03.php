<?php
// Charger le contenu de devis.json
$jsonFile = 'devis.json';
if (!file_exists($jsonFile)) {
    die("Le fichier devis.json est introuvable.");
}

$devisData = json_decode(file_get_contents($jsonFile), true);
if ($devisData === null) {
    die("Erreur lors du chargement de devis.json.");
}

// Extraire les données
$societe = $devisData['Societe'];
$client = $devisData['Client'];
$produits = $devisData['Produits'];
$devisInfo = $devisData['DevisInfo'];

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Calculer le total global
function calculerTotalGlobal($produits) {
    $totalHT = 0;
    $totalTTC = 0;
    foreach ($produits as $produit) {
        $totalHT += $produit['prixht'];
        $totalTTC += $produit['prixttc'];
    }
    return ['ht' => $totalHT, 'ttc' => $totalTTC];
}

$totalGlobal = calculerTotalGlobal($produits);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis - <?php echo htmlspecialchars($societe['RaisonSociale']); ?></title>
    <style>
        :root {
            --primary-color: #1a365d;
            --secondary-color: #2d5282;
            --accent-color: #e2e8f0;
            --text-color: #2d3748;
            --border-color: #e2e8f0;
            --background-light: #f8fafc;
            --success-color: #48bb78;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --page-width: 210mm;
            --page-height: 297mm;
            --page-margin: 0;
        }
@page {
    size: A4 portrait;
    
}


        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background: white;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .page {
            padding : 14mm ;
            width: 210mm;
            height: 280mm;
            
            position: relative;
            overflow: hidden;
            page-break-after: always;
            background: #F0F0F0;
            align-items: center;
        }

.cover-image {
    
    margin-top : 2%;
    flex-direction: column;
    display: flex; /* Active Flexbox */
    justify-content: center; /* Centre horizontalement */
    align-items: center; /* Centre verticalement */
    width: 100%; /* S'assurer qu'il prend toute la largeur disponible */
    height: 65%;
    background: rgba(255, 255, 255, 0.98);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
}

.cover-image img {
    margin : 2% ;
    display: block; /* Évite un espace blanc en bas de l'image */
    height: 45%; /* Garder le ratio */
}

        .cover-info {
            box-shadow: var(--box-shadow);

            height : 25%;
            background: rgba(255, 255, 255, 0.98);
            border-radius: var(--border-radius);
            padding : 2%;
            
            backdrop-filter: blur(5px);
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            
            align-items: center;
        }


        .cover-info img.logo {
            align-items: center;
            max-width : 80% ;
            display: block;
        }

         img.logo {
            margin-left : 10%;
            max-width= 100 % ;
            height: auto;
            display: block;
        }

        .cover-info .info-container {
            padding-left: 5%;
            width : 80% ;
        }

        .cover-info h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .cover-info p {
            margin: 0.5rem 0;
            font-size: 0.95rem;
            color: var(--text-color);
        }

        .cover-info strong {
            color: var(--primary-color);
            font-weight: 600;
        }



        .details-page .devis-details table {
            page-break-inside: auto;
        }

        .details-page .devis-details table thead {
            display: table-header-group;
        }



.devis-details table {
    width: 100%;
    table-layout: fixed; /* Fixe la largeur des colonnes */
}

.devis-details th:nth-child(1),
.devis-details td:nth-child(1) {
    width: 50%; /* Colonne Description */

    word-wrap: break-word; /* Permet de couper les mots longs */
    white-space: normal; /* Autorise les retours à la ligne */
}

.devis-details th:nth-child(2),
.devis-details td:nth-child(2),
.devis-details th:nth-child(3),
.devis-details td:nth-child(3) {
    width: 25%; /* Colonnes Prix HT et Prix TTC */
    text-align: center;
}

        .conditions-page {
            width: var(--page-width);
            height: var(--page-height);
            padding: 3rem;
            background: rgb(240, 240, 240);
            page-break-before: always;
            page-break-after: always;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .info-card h3 {
            color: var(--primary-color);
            font-size: 1.25rem;
            margin-bottom: 1rem;
            font-weight: 600;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 0.5rem;
        }

        .devis-details {
            margin-top: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            break-inside: avoid-start;
        }
        
        /* Style spécifique pour la continuation de la table sur une nouvelle page */
        .devis-details.continued {
            margin-top: 0;
            border-top: none;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }

        .devis-details h2 {
            background: var(--primary-color);
            color: white;
            padding: 1.25rem;
            font-size: 1.5rem;
            margin: 0;
        }

        .devis-details table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .devis-details th {
            background: var(--accent-color);
            color: var(--primary-color);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
        }

        .devis-details td {
            padding: 0.5rem;
            border-top: 1px solid var(--border-color);
        }

        .devis-details tr:last-child td {
            background: var(--accent-color);
            font-weight: 500;
        }

        .sub-product td {
            color: var(--secondary-color);
            font-size: 0.95rem;
            padding-left: 1rem;
        }

        .reference {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-top: 0.5rem;
        }

        .price {
            font-weight: 600;
            color: var(--primary-color);
        }

        .total-price {
            font-size: 1.2rem;
            color: var(--primary-color);
            font-weight: 700;
        }

        .total-section {
            background: var(--accent-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: auto;
        }

        .total-section h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .total-section p {
            margin: 0.8rem 0;
            font-size: 1.1rem;
        }

        .footer {

            text-align: center;
            color: var(--text-color);
            margin-top: 2rem;
        }

        .footer-content {
            border-top: 2px solid var(--border-color);
            padding-top: 2rem;
        }

        .footer p {
            margin-bottom: 1rem;
        }

        .print-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 1rem 2rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            box-shadow: var(--box-shadow);
            z-index: 1000;
        }

    </style>
</head>
<body>
    <div class="page">
        <div class="cover-info">
            <div class="logo-container">
                <img src="Logo Archimeuble.pdf" alt="<?php echo htmlspecialchars($societe['RaisonSociale']); ?>" class="logo">
            </div>
            <div class="info-container">
                <h3>Devis</h3>
                <p><strong>N° :</strong> <?php echo htmlspecialchars($devisInfo['NumeroDevis']); ?></p>
                <p><strong>Date :</strong> <?php echo formatDate($devisInfo['DateDevis']); ?></p>
                <p><strong>Validité :</strong> <?php echo htmlspecialchars($devisInfo['DureeValidite']); ?></p>
                <p><strong>Commercial métreur :</strong> <?php echo htmlspecialchars($devisInfo['NomCommercial']); ?></p>
            </div>
        </div>
        <div class="cover-image">
                <img src="uploads/photo1.png?<?php echo time(); ?>" alt="Image meuble">
                <img src="uploads/photo2.png?<?php echo time(); ?>" alt="Image meuble">
        </div>
    </div>

    <div class="page">
        <div class="info-grid">
            <div class="info-card">
                <h3>Client</h3>
                <p>
                    <strong><?php echo htmlspecialchars($client['Prenom'] . ' ' . $client['Nom']); ?></strong><br>
                    <?php echo htmlspecialchars($client['AdressePostale']); ?><br>
                    <strong>Email :</strong> <?php echo htmlspecialchars($client['Mail']); ?><br>
                    <strong>Tél :</strong> <?php echo htmlspecialchars($client['Numero']); ?>
                </p>
            </div>

            <div class="info-card">
                <h3>Entreprise</h3>
                <p>
                    <strong><?php echo htmlspecialchars($societe['RaisonSociale']); ?></strong><br>
                    <?php echo htmlspecialchars($societe['AdressePostale']); ?><br>
                    <strong>Siret :</strong> <?php echo htmlspecialchars($societe['siret']); ?><br>
                    <strong>Tél :</strong> <?php echo htmlspecialchars($societe['Numero']); ?><br>
                    <strong>Email :</strong> <?php echo htmlspecialchars($societe['AdresseMail']); ?>
                </p>
            </div>
        </div>

        <div class="devis-details">
            <h2>Détails du devis</h2>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Prix HT</th>
                        <th>Prix TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $firstProduct = true;
                    foreach ($produits as $produit): 
                    ?>
                    <tr>
                        <td data-label="Description">
                            <strong><?php echo htmlspecialchars($produit['Description']); ?></strong>
                            <?php if ($firstProduct): ?>
                                <div class="reference">Réf: <?php echo htmlspecialchars($produit['chaine']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Prix HT" class="price"><?php echo number_format($produit['prixht'], 2, ',', ' '); ?> €</td>
                        <td data-label="Prix TTC" class="price"><?php echo number_format($produit['prixttc'], 2, ',', ' '); ?> €</td>
                    </tr>
                    <?php if ($firstProduct): ?>
                        <?php foreach ($produit['sous_produit'] as $sousProduit): ?>
                        <tr class="sub-product">
                            <td data-label="Description">
                                <em>Détail - <?php echo htmlspecialchars($sousProduit['description']); ?></em>
                            </td>
                            <td data-label="Prix HT" class="price"><?php echo number_format($sousProduit['prixht'], 2, ',', ' '); ?> €</td>
                            <td data-label="Prix TTC" class="price"><?php echo number_format($sousProduit['prixttc'], 2, ',', ' '); ?> €</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php 
                    $firstProduct = false;
                    endforeach; 
                    ?>
                    <tr>
                        <td colspan="3" style="text-align: right;">
                            <div><strong>Total HT : </strong><span class="total-price"><?php echo number_format($totalGlobal['ht'], 2, ',', ' '); ?> €</span></div>
                            <div><strong>TVA (20%) : </strong><span class="total-price"><?php echo number_format($totalGlobal['ttc'] - $totalGlobal['ht'], 2, ',', ' '); ?> €</span></div>
                            <div><strong>Total TTC : </strong><span class="total-price"><?php echo number_format($totalGlobal['ttc'], 2, ',', ' '); ?> €</span></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="page">
        <div class="total-section">
            <h3>Conditions de règlement</h3>
            <p>
                <strong>Acompte à la commande:</strong> <?php echo number_format($totalGlobal['ttc'] * 0.30, 2, ',', ' '); ?> € (30%)<br>
                <strong>Solde à la livraison :</strong> <?php echo number_format($totalGlobal['ttc'] * 0.70, 2, ',', ' '); ?> € (70%)
            </p>
            <p style="margin-top: 1.5rem;">
                <strong>IBAN :</strong> IBAN :FR76 1350 7001 2832 0048 6215 753<br>
                <strong>BIC/SWIFT :</strong> CCBPFRPPLIL    TVA non applicable 293B du CGI

            </p>
        </div>

        <footer class="footer">
            <div class="footer-content">
                <p>Produit dans le Nord-Pas-de-Calais</p>
                <p>Ce devis a été établi par ©Gauthier Hue</p>
                <p></p>
                <p></p>
            </div>
        </footer>
    </div>

    <button onclick="window.print()" class="print-button">
        Imprimer le devis
    </button>

</body>
</html>