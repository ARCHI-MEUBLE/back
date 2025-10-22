<?php
/**
 * Test d'insertion directe dans la base de données
 */

require_once __DIR__ . '/backend/core/Database.php';
require_once __DIR__ . '/backend/models/Model.php';

echo "=== Test d'insertion de modèle ===\n\n";

$model = new Model();

echo "Tentative d'insertion...\n";

$modelId = $model->create(
    'Test Melomania',
    'Test description ssjsj',
    'M1(1700,500,730)EFH2(F,T,F)',
    null,
    '/images/test.jpg'
);

echo "Résultat: " . ($modelId ? "SUCCESS - ID: $modelId" : "FAILED") . "\n";

if ($modelId) {
    echo "\nModèle créé:\n";
    $createdModel = $model->getById($modelId);
    print_r($createdModel);
} else {
    echo "\nÉchec de la création du modèle\n";
}
