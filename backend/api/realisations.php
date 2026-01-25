<?php
/**
 * ArchiMeuble - Endpoint public des réalisations
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../core/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = Database::getInstance()->getPDO();

// Récupérer toutes les réalisations
$stmt = $db->query('SELECT * FROM realisations ORDER BY created_at DESC');
$realisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pour chaque réalisation, récupérer ses images
foreach ($realisations as &$realisation) {
    $stmt = $db->prepare('SELECT * FROM realisation_images WHERE realisation_id = ? ORDER BY ordre ASC');
    $stmt->execute([$realisation['id']]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si l'image principale existe dans realisations mais pas dans realisation_images,
    // l'ajouter en premier dans le tableau
    $mainImageUrl = $realisation['image_url'];
    if (!empty($mainImageUrl)) {
        $mainImageExists = false;
        foreach ($images as $img) {
            if ($img['image_url'] === $mainImageUrl) {
                $mainImageExists = true;
                break;
            }
        }

        // Ajouter l'image principale en premier si elle n'est pas déjà dans la liste
        if (!$mainImageExists) {
            array_unshift($images, [
                'id' => 0,
                'realisation_id' => $realisation['id'],
                'image_url' => $mainImageUrl,
                'legende' => null,
                'ordre' => -1
            ]);
        }
    }

    $realisation['images'] = $images;

    // Garder image_url pour compatibilité (première image)
    if (!empty($realisation['images'])) {
        $realisation['image_url'] = $realisation['images'][0]['image_url'];
    }
}

echo json_encode(['success' => true, 'realisations' => $realisations]);
?>
