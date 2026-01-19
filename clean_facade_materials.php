<?php
/**
 * Script pour nettoyer les matériaux de façades
 * Vide les texture_url des matériaux qui ont une couleur
 */

require_once __DIR__ . '/backend/config/database.php';

try {
    $db = getDbConnection();
    
    echo "=== Nettoyage des matériaux de façades ===\n\n";
    
    // Récupérer tous les matériaux
    $result = $db->query('SELECT id, name, color_hex, texture_url FROM facade_materials');
    
    $toClean = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "ID {$row['id']}: {$row['name']}\n";
        echo "  - color_hex: {$row['color_hex']}\n";
        echo "  - texture_url: " . ($row['texture_url'] ?: '(vide)') . "\n";
        
        // Si le matériau a une texture_url qui n'est PAS une vraie image uploadée
        // (commence par /back/textures/texture_), on la vide
        if ($row['texture_url'] && strpos($row['texture_url'], '/back/textures/texture_') === false) {
            // C'est une ancienne texture (PNG dans textures/), on la garde
            echo "  → GARDER la texture\n\n";
        } elseif ($row['texture_url'] && strpos($row['texture_url'], '/back/textures/texture_') !== false) {
            // C'est une texture uploadée, on la garde
            echo "  → GARDER la texture uploadée\n\n";
        } else {
            // Pas de texture ou vide, on vide pour être sûr
            $toClean[] = $row['id'];
            echo "  → NETTOYER (vider texture_url)\n\n";
        }
    }
    
    if (empty($toClean)) {
        echo "Aucun matériau à nettoyer.\n";
        exit(0);
    }
    
    echo "=== Nettoyage de " . count($toClean) . " matériaux ===\n";
    
    foreach ($toClean as $id) {
        $stmt = $db->prepare('UPDATE facade_materials SET texture_url = "" WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        echo "✓ Matériau ID $id nettoyé\n";
    }
    
    echo "\n=== Nettoyage terminé ===\n";
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
