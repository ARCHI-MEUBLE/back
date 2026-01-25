<?php
/**
 * API: Envoyer l'email de confirmation de commande
 * POST /api/orders/send-confirmation
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../services/EmailService.php';
require_once __DIR__ . '/../../core/Database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $orderId = $data['order_id'] ?? null;

    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['error' => 'order_id requis']);
        exit;
    }

    $order = new Order();
    $db = Database::getInstance();

    // Vérifier que la commande appartient au client
    $orderData = $order->getById($orderId);
    if (!$orderData || (int)$orderData['customer_id'] !== (int)$_SESSION['customer_id']) {
        http_response_code(404);
        echo json_encode(['error' => 'Commande non trouvée']);
        exit;
    }

    // Vérifier si un email de confirmation a déjà été envoyé
    if (!empty($orderData['confirmation_email_sent'])) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Email déjà envoyé']);
        exit;
    }

    // Récupérer les infos du client
    $customer = $db->queryOne("SELECT email, first_name, last_name FROM customers WHERE id = ?", [$_SESSION['customer_id']]);

    if (!$customer) {
        http_response_code(404);
        echo json_encode(['error' => 'Client non trouvé']);
        exit;
    }

    // Récupérer tous les détails de la commande
    $items = $order->getOrderItems($orderId);
    $samples = $order->getOrderSamples($orderId);
    $catalogueItems = $order->getOrderCatalogueItems($orderId);
    $facadeItems = $order->getOrderFacadeItems($orderId);

    // Préparer le contenu de l'email
    $emailService = new EmailService();

    $customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    if (empty($customerName)) {
        $customerName = 'Client';
    }

    // Déterminer le type de commande
    $hasConfigurations = !empty($items);
    $hasCatalogueItems = !empty($catalogueItems);
    $hasFacades = !empty($facadeItems);
    $hasSamples = !empty($samples);
    $total = $orderData['total_amount'] ?? $orderData['total'] ?? 0;
    $isSamplesOnly = $hasSamples && !$hasConfigurations && !$hasCatalogueItems && !$hasFacades;

    // Construire le récapitulatif des articles
    $itemsHtml = '';

    // Meubles sur mesure
    if ($hasConfigurations) {
        $itemsHtml .= '<h4 style="margin: 20px 0 10px 0; color: #706F6C; font-size: 12px; text-transform: uppercase;">Meubles sur mesure</h4>';
        foreach ($items as $item) {
            $configData = is_string($item['config_data']) ? json_decode($item['config_data'], true) : $item['config_data'];
            $itemName = $configData['name'] ?? $item['name'] ?? 'Configuration';
            $itemTotal = ($item['total_price'] ?? ($item['unit_price'] * $item['quantity']));
            $itemsHtml .= '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #E8E6E3;">';
            $itemsHtml .= '<div><strong>' . htmlspecialchars($itemName) . '</strong><br><span style="color: #706F6C; font-size: 14px;">Qté: ' . $item['quantity'] . '</span></div>';
            $itemsHtml .= '<div style="text-align: right;"><strong>' . number_format($itemTotal, 2, ',', ' ') . ' €</strong></div>';
            $itemsHtml .= '</div>';
        }
    }

    // Façades
    if ($hasFacades) {
        $itemsHtml .= '<h4 style="margin: 20px 0 10px 0; color: #706F6C; font-size: 12px; text-transform: uppercase;">Façades sur mesure</h4>';
        foreach ($facadeItems as $facade) {
            $config = is_string($facade['config_data']) ? json_decode($facade['config_data'], true) : $facade['config_data'];
            $materialName = $config['material']['name'] ?? 'Matériau';
            $width = isset($config['width']) ? ($config['width'] / 10) : 0;
            $height = isset($config['height']) ? ($config['height'] / 10) : 0;
            $depth = $config['depth'] ?? 19;
            $facadeTotal = ($facade['total_price'] ?? ($facade['unit_price'] * $facade['quantity']));

            $itemsHtml .= '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #E8E6E3;">';
            $itemsHtml .= '<div><strong>Façade ' . $width . ' × ' . $height . ' cm · ' . $depth . ' mm</strong><br>';
            $itemsHtml .= '<span style="color: #706F6C; font-size: 14px;">' . htmlspecialchars($materialName) . ' · Qté: ' . $facade['quantity'] . '</span></div>';
            $itemsHtml .= '<div style="text-align: right;"><strong>' . number_format($facadeTotal, 2, ',', ' ') . ' €</strong></div>';
            $itemsHtml .= '</div>';
        }
    }

    // Articles boutique
    if ($hasCatalogueItems) {
        $itemsHtml .= '<h4 style="margin: 20px 0 10px 0; color: #706F6C; font-size: 12px; text-transform: uppercase;">Articles boutique</h4>';
        foreach ($catalogueItems as $catItem) {
            $itemName = $catItem['name'] ?? $catItem['item_name'] ?? 'Article';
            $catTotal = ($catItem['total_price'] ?? ($catItem['unit_price'] * $catItem['quantity']));
            $itemsHtml .= '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #E8E6E3;">';
            $itemsHtml .= '<div><strong>' . htmlspecialchars($itemName) . '</strong>';
            if (!empty($catItem['variation_name'])) {
                $itemsHtml .= '<br><span style="color: #706F6C; font-size: 14px;">' . htmlspecialchars($catItem['variation_name']) . '</span>';
            }
            $itemsHtml .= '<br><span style="color: #706F6C; font-size: 14px;">Qté: ' . $catItem['quantity'] . '</span></div>';
            $itemsHtml .= '<div style="text-align: right;"><strong>' . number_format($catTotal, 2, ',', ' ') . ' €</strong></div>';
            $itemsHtml .= '</div>';
        }
    }

    // Échantillons
    if ($hasSamples) {
        // Calculer le total des échantillons pour savoir s'ils sont gratuits
        $samplesTotal = 0;
        foreach ($samples as $sample) {
            $samplesTotal += ($sample['price'] ?? 0) * $sample['quantity'];
        }
        $areSamplesFree = $samplesTotal == 0;

        $sampleHeaderColor = $areSamplesFree ? '#059669' : '#706F6C';
        $sampleHeaderText = $areSamplesFree ? 'Échantillons gratuits' : 'Échantillons';
        $itemsHtml .= '<h4 style="margin: 20px 0 10px 0; color: ' . $sampleHeaderColor . '; font-size: 12px; text-transform: uppercase;">' . $sampleHeaderText . '</h4>';

        foreach ($samples as $sample) {
            $samplePrice = ($sample['price'] ?? 0) * $sample['quantity'];
            $priceDisplay = $samplePrice == 0
                ? '<strong style="color: #059669;">Gratuit</strong>'
                : '<strong>' . number_format($samplePrice, 2, ',', ' ') . ' €</strong>';

            $itemsHtml .= '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #E8E6E3;">';
            $itemsHtml .= '<div><strong>' . htmlspecialchars($sample['sample_name'] ?? 'Échantillon') . '</strong><br>';
            $itemsHtml .= '<span style="color: #706F6C; font-size: 14px;">' . htmlspecialchars($sample['material'] ?? '') . ' · Qté: ' . $sample['quantity'] . '</span></div>';
            $itemsHtml .= '<div style="text-align: right;">' . $priceDisplay . '</div>';
            $itemsHtml .= '</div>';
        }
    }

    // Déterminer le sujet et le message
    if ($isSamplesOnly) {
        $subject = "Vos échantillons sont en préparation - #{$orderData['order_number']}";
        $intro = "Vos échantillons ont bien été commandés et seront expédiés sous 24-48h.";
    } elseif ($hasConfigurations) {
        $subject = "Confirmation de commande #{$orderData['order_number']}";
        $intro = "Votre commande a été confirmée. Nous allons commencer la fabrication de vos meubles sur mesure.";
    } elseif ($hasFacades) {
        $subject = "Confirmation de commande #{$orderData['order_number']}";
        $intro = "Votre commande a été confirmée. Vos façades sur mesure vont être fabriquées.";
    } else {
        $subject = "Confirmation de commande #{$orderData['order_number']}";
        $intro = "Votre commande a été confirmée. Vos articles vont être préparés pour l'expédition.";
    }

    $totalFormatted = $total == 0 ? 'Gratuit' : number_format($total, 2, ',', ' ') . ' €';

    // URL du frontend (utiliser la variable d'environnement ou fallback)
    $frontendUrl = getenv('FRONTEND_URL') ?: 'http://localhost:3000';

    // Envoyer l'email
    $htmlContent = '
    <div style="font-family: Georgia, serif; max-width: 600px; margin: 0 auto; padding: 40px 20px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #1A1917; font-size: 28px; margin: 0;">Merci pour votre commande !</h1>
            <p style="color: #706F6C; margin-top: 10px;">' . htmlspecialchars($intro) . '</p>
        </div>

        <div style="background: #F5F5F4; padding: 20px; margin-bottom: 30px;">
            <p style="margin: 0; color: #706F6C; font-size: 12px; text-transform: uppercase;">Numéro de commande</p>
            <p style="margin: 5px 0 0 0; color: #1A1917; font-size: 20px; font-weight: bold;">' . htmlspecialchars($orderData['order_number']) . '</p>
        </div>

        <div style="margin-bottom: 30px;">
            <h3 style="color: #1A1917; font-size: 16px; margin-bottom: 15px;">Récapitulatif</h3>
            ' . $itemsHtml . '

            <div style="display: flex; justify-content: space-between; padding: 20px 0; border-top: 2px solid #1A1917; margin-top: 20px;">
                <strong style="font-size: 18px;">Total</strong>
                <strong style="font-size: 18px;">' . $totalFormatted . '</strong>
            </div>
        </div>

        <div style="margin-bottom: 30px;">
            <h3 style="color: #1A1917; font-size: 16px; margin-bottom: 10px;">Adresse de livraison</h3>
            <p style="color: #706F6C; margin: 0;">' . nl2br(htmlspecialchars($orderData['shipping_address'])) . '</p>
        </div>

        <div style="text-align: center; margin-top: 40px;">
            <a href="' . $frontendUrl . '/account?section=orders" style="display: inline-block; background: #1A1917; color: white; padding: 15px 30px; text-decoration: none; font-weight: bold;">
                Suivre ma commande
            </a>
        </div>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #E8E6E3; text-align: center; color: #706F6C; font-size: 12px;">
            <p>ArchiMeuble - Menuisiers à Lille</p>
            <p>30 Rue Henri Regnault, 59000 Lille</p>
            <p>06 01 06 28 67 | pro.archimeuble@gmail.com</p>
        </div>
    </div>';

    $result = $emailService->send(
        $customer['email'],
        $subject,
        $htmlContent
    );

    if ($result) {
        // Marquer l'email comme envoyé
        $db->execute("UPDATE orders SET confirmation_email_sent = 1 WHERE id = ?", [$orderId]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Email de confirmation envoyé']);
    } else {
        throw new Exception("Échec de l'envoi de l'email");
    }

} catch (Exception $e) {
    error_log("Erreur send-confirmation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
