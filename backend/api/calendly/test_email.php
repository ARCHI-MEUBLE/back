<?php
/**
 * Script de test pour vérifier l'envoi d'emails
 */

require_once __DIR__ . '/EmailService.php';

echo "=== Test d'envoi d'email ===\n\n";

// Email de test
$testEmail = 'benskotlemogo@gmail.com';
$testName = 'Ben Skot';

echo "Configuration SMTP :\n";
echo "- Host: smtp.gmail.com\n";
echo "- Port: 587\n";
echo "- Username: benskotlemogo@gmail.com\n";
echo "- Destinataire test: $testEmail\n\n";

try {
    $emailService = new EmailService();

    echo "Envoi d'un email de test...\n";

    $result = $emailService->sendConfirmationEmail(
        $testEmail,
        $testName,
        'Consultation téléphonique - 30 min',
        '15/11/2025 à 14:00',
        '14:30',
        'https://archimeuble.com/config/test'
    );

    if ($result) {
        echo "✅ Email envoyé avec succès!\n";
        echo "Vérifiez votre boîte de réception : $testEmail\n";
    } else {
        echo "❌ Échec de l'envoi de l'email\n";
        echo "Vérifiez les logs pour plus de détails\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Fin du test ===\n";
?>
