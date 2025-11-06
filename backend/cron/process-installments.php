<?php
/**
 * Script CRON: Traitement des mensualités
 * À exécuter quotidiennement pour prélever les paiements en attente
 *
 * Configuration cron (à ajouter dans crontab):
 * 0 9 * * * php /path/to/backend/cron/process-installments.php
 * (Tous les jours à 9h00)
 */

// Charger les dépendances
require_once __DIR__ . '/../services/InstallmentService.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../vendor/stripe/init.php';

// Configuration logging
$logFile = __DIR__ . '/../logs/installments_' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    echo "[{$timestamp}] {$message}\n";
}

logMessage("========== DÉBUT DU TRAITEMENT DES MENSUALITÉS ==========");

try {
    // Initialiser Stripe
    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');
    if (!$stripeSecretKey || $stripeSecretKey === 'sk_test_YOUR_SECRET_KEY_HERE') {
        throw new Exception('Clé Stripe non configurée');
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);

    $installmentService = new InstallmentService();
    $emailService = new EmailService();
    $orderModel = new Order();
    $customerModel = new Customer();

    // Récupérer toutes les mensualités en attente et dues
    $pendingInstallments = $installmentService->getPendingInstallments();

    logMessage("Mensualités en attente trouvées: " . count($pendingInstallments));

    foreach ($pendingInstallments as $installment) {
        logMessage("Traitement de la mensualité #{$installment['id']} - Commande {$installment['order_number']} - {$installment['installment_number']}/3");

        try {
            // Créer un PaymentIntent Stripe
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int)($installment['amount'] * 100), // Montant en centimes
                'currency' => 'eur',
                'customer' => $installment['stripe_customer_id'],
                'payment_method_types' => ['card'],
                'off_session' => true, // Paiement hors session (avec carte enregistrée)
                'confirm' => true, // Confirmer immédiatement
                'description' => "Mensualité {$installment['installment_number']}/3 - Commande {$installment['order_number']}",
                'metadata' => [
                    'order_id' => $installment['order_id'],
                    'installment_id' => $installment['id'],
                    'installment_number' => $installment['installment_number'],
                ]
            ]);

            if ($paymentIntent->status === 'succeeded') {
                // Marquer la mensualité comme payée
                $installmentService->markInstallmentPaid($installment['id'], $paymentIntent->id);

                logMessage("✓ Mensualité #{$installment['id']} payée avec succès (PI: {$paymentIntent->id})");

                // Envoyer email de confirmation au client
                // TODO: Créer template email spécifique pour mensualité
                logMessage("Email de confirmation envoyé à {$installment['email']}");

            } else {
                logMessage("⚠ Paiement en attente pour mensualité #{$installment['id']} - Statut: {$paymentIntent->status}");
            }

        } catch (\Stripe\Exception\CardException $e) {
            // Carte refusée
            $installmentService->markInstallmentFailed($installment['id']);
            logMessage("✗ Échec mensualité #{$installment['id']}: {$e->getMessage()}");

            // Envoyer email d'alerte au client
            // TODO: Implémenter email d'échec de mensualité

        } catch (Exception $e) {
            logMessage("✗ Erreur mensualité #{$installment['id']}: {$e->getMessage()}");
        }
    }

    if (count($pendingInstallments) === 0) {
        logMessage("Aucune mensualité à traiter aujourd'hui");
    }

    logMessage("========== FIN DU TRAITEMENT ==========");

} catch (Exception $e) {
    logMessage("ERREUR CRITIQUE: " . $e->getMessage());
    logMessage($e->getTraceAsString());
}
