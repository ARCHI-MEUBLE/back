<?php
/**
 * Service d'envoi d'emails
 * Gère l'envoi d'emails transactionnels via SMTP
 */

class EmailService {
    private $from;
    private $adminEmail;
    private $siteName = 'ArchiMeuble';

    public function __construct() {
        // Recharger les variables d'environnement au cas où
        if (file_exists(__DIR__ . '/../config/env.php')) {
            require_once __DIR__ . '/../config/env.php';
        }
        $this->from = getenv('SMTP_FROM_EMAIL') ?: 'noreply@archimeuble.com';
        $this->adminEmail = getenv('ADMIN_EMAIL') ?: 'pro.archimeuble@gmail.com';
        
        if (getenv('ADMIN_EMAIL')) {
            error_log("EmailService: Admin email loaded from ENV: " . getenv('ADMIN_EMAIL'));
        } else {
            error_log("EmailService: ADMIN_EMAIL not found in ENV, using default: " . $this->adminEmail);
        }
    }

    /**
     * Envoie une notification de nouvelle configuration à l'admin
     */
    public function sendNewConfigurationNotificationToAdmin($config, $customer) {
        if (!$config || !isset($config['id'])) {
            error_log("Cannot send notification: invalid config data");
            return false;
        }

        $configName = $config['name'] ?? "Configuration #{$config['id']}";
        $subject = "Nouveau projet client : {$configName} - {$customer['first_name']} {$customer['last_name']}";

        $body = $this->getAdminConfigurationNotificationTemplate($config, $customer);

        error_log("Attempting to send configuration notification email to admin: {$this->adminEmail}");
        return $this->sendEmail($this->adminEmail, $subject, $body);
    }

    /**
     * Template HTML pour notification admin de nouvelle configuration
     */
    private function getAdminConfigurationNotificationTemplate($config, $customer) {
        $price = $config['price'] ?? 0;
        $priceFormatted = number_format($price, 2, ',', ' ') . ' €';
        $frontendUrl = getenv('FRONTEND_URL') ?: 'http://localhost:3000';
        $backendUrl = getenv('BACKEND_URL') ?: 'http://localhost:8000';

        // Déterminer le type de meuble (M1-M5)
        $type = $config['template_id'] ?? null;
        if (!$type && isset($config['prompt'])) {
            preg_match('/^(M[1-5])/', $config['prompt'], $matches);
            $type = $matches[1] ?? 'M1';
        } else {
            $type = $type ?: 'M1';
        }

        $viewUrl = "{$frontendUrl}/configurator/{$type}?mode=view&configId={$config['id']}";

        // Extraire tous les détails techniques
        $detailsHtml = "";
        $accessoriesHtml = "";
        $dimensionsHtml = "";
        $multiColorHtml = "";
        $accessoriesList = []; // Initialize to prevent undefined variable errors

        if (isset($config['config_string'])) {
            $data = json_decode($config['config_string'], true);
            if ($data) {
                // Dimensions
                $dim = $data['dimensions'] ?? [];
                $w = $dim['width'] ?? 0;
                $h = $dim['height'] ?? 0;
                $d = $dim['depth'] ?? 0;

                $dimensionsHtml = "
                    <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                        <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>📐 Dimensions de l'ouvrage</h4>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Largeur</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$w} mm</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Hauteur</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$h} mm</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px;'>Profondeur</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$d} mm</td>
                            </tr>
                        </table>
                    </div>
                ";

                // Styling et matériaux
                $styling = $data['styling'] ?? [];
                $material = $styling['materialLabel'] ?? ($styling['materialKey'] ?? 'Standard');
                $finish = $styling['finish'] ?? 'Non spécifiée';
                $color = $styling['colorLabel'] ?? ($styling['color'] ?? 'Standard');
                $socle = $styling['socle'] ?? 'none';
                $socleLabel = $socle === 'metal' ? 'Socle métal noir' : ($socle === 'wood' ? 'Socle bois' : 'Sans socle (pose au sol)');

                $detailsHtml = "
                    <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                        <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>🎨 Finitions & Matériaux</h4>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Matériau principal</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$material}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Finition</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$finish}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Couleur dominante</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$color}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px;'>Type de socle</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$socleLabel}</td>
                            </tr>
                        </table>
                    </div>
                ";

                // Couleurs de composants (si multi-couleur)
                if (isset($data['useMultiColor']) && $data['useMultiColor'] && isset($data['componentColors'])) {
                    $comp = $data['componentColors'];
                    $multiColorHtml = "
                        <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                            <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>🌈 Détails Multi-couleurs</h4>
                            <table style='width: 100%; border-collapse: collapse;'>
                    ";
                    $labels = [
                        'structure' => 'Structure',
                        'drawers' => 'Tiroirs',
                        'doors' => 'Portes',
                        'shelves' => 'Étagères',
                        'back' => 'Fond',
                        'base' => 'Socle'
                    ];
                    foreach ($comp as $key => $val) {
                        if (isset($labels[$key])) {
                            $cLabel = $val['colorLabel'] ?? ($val['hex'] ?? 'Standard');
                            $multiColorHtml .= "
                                <tr>
                                    <td style='padding: 8px 0; color: #706F6C; font-size: 13px; border-bottom: 1px solid #F0EFEA;'>{$labels[$key]}</td>
                                    <td style='padding: 8px 0; color: #1A1917; text-align: right; font-weight: 500;'>{$cLabel}</td>
                                </tr>
                            ";
                        }
                    }
                    $multiColorHtml .= "</table></div>";
                }

                // Features (portes)
                $features = $data['features'] ?? [];
                $doorType = $features['doorType'] ?? 'none';
                $doorSide = $features['doorSide'] ?? 'none';

                if ($doorType !== 'none') {
                    $doorTypeLabel = 'Aucune';
                    if ($doorType === 'sliding') $doorTypeLabel = 'Coulissante';
                    else if ($doorType === 'hinged') $doorTypeLabel = 'Battante';
                    else if ($doorType === 'lift') $doorTypeLabel = 'Relevable';
                    else if ($doorType === 'double') $doorTypeLabel = 'Double porte';
                    else if ($doorType === 'single') $doorTypeLabel = 'Porte simple';

                    $doorSideLabel = 'N/A';
                    if ($doorSide === 'left') $doorSideLabel = 'Gauche';
                    else if ($doorSide === 'right') $doorSideLabel = 'Droite';

                    $detailsHtml .= "
                        <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                            <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>🚪 Système d'ouverture</h4>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Type de porte</td>
                                    <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$doorTypeLabel}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px 0; color: #706F6C; font-size: 14px;'>Sens d'ouverture</td>
                                    <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$doorSideLabel}</td>
                                </tr>
                            </table>
                        </div>
                    ";
                }

                // Zones et accessoires
                $advancedZones = $data['advancedZones'] ?? null;
                if ($advancedZones) {
                    $accessoriesList = $this->extractAccessoriesFromZone($advancedZones);
                    if (!empty($accessoriesList)) {
                        $accessoriesHtml = "
                            <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                                <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>📦 Aménagements intérieurs</h4>
                                <table style='width: 100%; border-collapse: collapse;'>
                        ";
                        foreach ($accessoriesList as $acc) {
                            $accessoriesHtml .= "
                                <tr>
                                    <td style='padding: 8px 0; color: #1A1917; font-size: 13px; border-bottom: 1px solid #F0EFEA;'>
                                        <span style='color: #8B7355; margin-right: 8px;'>•</span> {$acc}
                                    </td>
                                </tr>
                            ";
                        }
                        $accessoriesHtml .= "</table></div>";
                    }
                }
            }
        }

        // Ajouter le prompt technique (plus discret)
        $promptHtml = "";
        if (isset($config['prompt'])) {
            $promptHtml = "
                <div style='margin-top: 32px; padding: 16px; background-color: #F8F8F8; border-radius: 4px;'>
                    <p style='margin: 0 0 8px 0; color: #A8A7A3; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;'>Code de fabrication (Prompt)</p>
                    <code style='font-family: \"JetBrains Mono\", monospace; font-size: 11px; color: #706F6C; word-break: break-all;'>{$config['prompt']}</code>
                </div>
            ";
        }

        // Lien vers le plan 2D (DXF) - Très visible
        $dxfLinkHtml = "";
        $dxfUrl = $config['dxf_url'] ?? null;
        // Préférer l'URL du frontend pour les fichiers statiques car ils sont dans front/public
        $dxfDownloadUrl = $dxfUrl ? "{$frontendUrl}{$dxfUrl}" : "{$backendUrl}/api/files/dxf?id={$config['id']}";
        
        // Bloc 2D stylisé avec détails des compartiments
        $dxfLinkHtml = "
            <div style='background-color: #1A1917; padding: 32px; border-radius: 4px; margin-bottom: 24px;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <h4 style='margin: 0 0 8px 0; color: #FFFFFF; font-size: 16px; font-weight: 600;'>PLAN DE FABRICATION 2D</h4>
                    <div style='height: 2px; width: 40px; margin: 0 auto 16px auto; background-color: #8B7355;'></div>
                    <p style='color: #A8A7A3; font-size: 13px; line-height: 1.5;'>Détails des aménagements par compartiment pour la mise en production.</p>
                </div>

                <div style='background-color: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 20px; margin-bottom: 24px;'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        " . (!empty($accessoriesList) ? implode('', array_map(function($acc) {
                            return "<tr>
                                <td style='padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); color: #FFFFFF; font-size: 13px;'>
                                    <span style='color: #8B7355; margin-right: 10px;'>→</span> {$acc}
                                </td>
                            </tr>";
                        }, $accessoriesList)) : "<tr><td style='color: #A8A7A3; font-size: 13px; text-align: center;'>Aucun aménagement spécifique (caisson vide)</td></tr>") . "
                    </table>
                </div>

                <div style='text-align: center;'>
                    <a href='{$dxfDownloadUrl}' style='display: inline-block; border: 1px solid #8B7355; color: #8B7355; padding: 12px 24px; text-decoration: none; font-weight: 600; font-size: 12px; border-radius: 2px; text-transform: uppercase; letter-spacing: 0.1em;'>
                        Télécharger le fichier .DXF
                    </a>
                </div>
            </div>
        ";

        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Nouveau projet ArchiMeuble</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #F5F5F4; color: #1A1917;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #F5F5F4; padding: 40px 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #FFFFFF; border: 1px solid #E8E6E3; box-shadow: 0 4px 24px rgba(0,0,0,0.04);'>
                            <!-- Header -->
                            <tr>
                                <td style='padding: 40px; border-bottom: 1px solid #F0EFEA; text-align: center;'>
                                    <div style='text-transform: uppercase; letter-spacing: 0.3em; font-size: 10px; font-weight: 700; color: #8B7355; margin-bottom: 16px;'>Notification Admin</div>
                                    <h1 style='margin: 0; font-size: 24px; font-weight: 400; font-family: serif;'>Nouveau projet client</h1>
                                </td>
                            </tr>

                            <!-- Client Info -->
                            <tr>
                                <td style='padding: 40px; background-color: #FAFAF9;'>
                                    <h2 style='margin: 0 0 24px 0; font-size: 18px; font-weight: 600;'>" . ($config['name'] ?? "Configuration sans nom") . "</h2>
                                    
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td style='padding-bottom: 12px;'>
                                                <div style='font-size: 11px; text-transform: uppercase; color: #706F6C; margin-bottom: 4px;'>Client</div>
                                                <div style='font-size: 16px; font-weight: 500;'>{$customer['first_name']} {$customer['last_name']}</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding-bottom: 12px;'>
                                                <div style='font-size: 11px; text-transform: uppercase; color: #706F6C; margin-bottom: 4px;'>Email</div>
                                                <div style='font-size: 15px;'><a href='mailto:{$customer['email']}' style='color: #1A1917; text-decoration: underline;'>{$customer['email']}</a></div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div style='font-size: 11px; text-transform: uppercase; color: #706F6C; margin-bottom: 4px;'>Téléphone</div>
                                                <div style='font-size: 15px;'>" . (!empty($customer['phone']) ? "<a href='tel:{$customer['phone']}' style='color: #1A1917; text-decoration: none;'>{$customer['phone']}</a>" : 'Non renseigné') . "</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Price Banner -->
                            <tr>
                                <td style='padding: 32px 40px; border-top: 1px solid #F0EFEA; border-bottom: 1px solid #F0EFEA;'>
                                    <table width='100%'>
                                        <tr>
                                            <td style='font-size: 16px; font-weight: 600;'>Estimation du projet</td>
                                            <td align='right' style='font-size: 24px; font-weight: 700; color: #8B7355;'>{$priceFormatted}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Specifications -->
                            <tr>
                                <td style='padding: 40px;'>
                                    <h3 style='margin: 0 0 24px 0; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;'>Spécifications techniques</h3>
                                    
                                    {$dimensionsHtml}
                                    {$detailsHtml}
                                    {$multiColorHtml}
                                    {$accessoriesHtml}
                                    
                                    <!-- DXF Download Section -->
                                    {$dxfLinkHtml}

                                    <!-- 3D View Button -->
                                    <div style='margin-top: 32px; text-align: center;'>
                                        <a href='{$viewUrl}' style='display: block; background-color: #1A1917; color: #FFFFFF; padding: 18px; text-decoration: none; font-weight: 600; font-size: 14px; border-radius: 2px; letter-spacing: 0.1em;'>
                                            VOIR LA CONFIGURATION 3D INTERACTIVE
                                        </a>
                                    </div>

                                    {$promptHtml}
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='padding: 32px 40px; background-color: #FAFAF9; border-top: 1px solid #F0EFEA; text-align: center;'>
                                    <p style='margin: 0; font-size: 12px; color: #706F6C; line-height: 1.6;'>
                                        Ce projet est en attente de validation dans votre tableau de bord.<br>
                                        Connectez-vous pour envoyer le lien de paiement au client.
                                    </p>
                                    <div style='margin-top: 24px;'>
                                        <img src='{$frontendUrl}/logo.png' alt='ArchiMeuble' height='24' style='opacity: 0.5;'>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        
                        <p style='margin-top: 24px; font-size: 11px; color: #A8A7A3; text-align: center;'>
                            © " . date('Y') . " ArchiMeuble — Manufacture de mobilier sur mesure à Lille.
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Extrait récursivement les accessoires d'une zone
     */
    private function extractAccessoriesFromZone($zone, $prefix = '') {
        $accessories = [];

        if (!$zone || !is_array($zone)) {
            return $accessories;
        }

        $zoneName = $zone['name'] ?? ($prefix === '' ? 'Meuble' : 'Zone');
        $currentPrefix = $prefix ? "{$prefix} > {$zoneName}" : $zoneName;

        // Si c'est une feuille, on regarde son contenu direct
        if (isset($zone['type']) && $zone['type'] === 'leaf') {
            $content = $zone['content'] ?? 'empty';
            if ($content !== 'empty') {
                $contentLabel = $this->getAccessoryLabel($content);
                $accessories[] = "{$currentPrefix} : {$contentLabel}";
            }
        }

        // Extraire les accessoires de la zone actuelle (tableau additionnel)
        if (isset($zone['accessories']) && is_array($zone['accessories'])) {
            foreach ($zone['accessories'] as $acc) {
                $accType = $acc['type'] ?? 'inconnu';
                $accLabel = $this->getAccessoryLabel($accType);
                $accessories[] = "{$currentPrefix} : {$accLabel}";
            }
        }

        // Traiter les sous-zones (horizontales et verticales)
        if (isset($zone['children']) && is_array($zone['children'])) {
            $childCount = count($zone['children']);
            foreach ($zone['children'] as $idx => $child) {
                $childName = $child['name'] ?? "Section " . ($idx + 1);
                $childAccessories = $this->extractAccessoriesFromZone($child, "{$currentPrefix} > {$childName}");
                $accessories = array_merge($accessories, $childAccessories);
            }
        }

        return $accessories;
    }

    /**
     * Traduit le type d'accessoire en français
     */
    private function getAccessoryLabel($type) {
        $labels = [
            'shelf' => 'Étagère',
            'drawer' => 'Tiroir',
            'hanging_rod' => 'Barre de penderie',
            'door' => 'Porte',
            'basket' => 'Panier',
            'tray' => 'Plateau',
            'divider' => 'Séparateur',
            'shoe_rack' => 'Range-chaussures',
            'hanger' => 'Portant',
            'dressing' => 'Aménagement penderie',
            'empty' => 'Niche vide'
        ];

        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Envoie un email de confirmation de commande au client
     */
    public function sendOrderConfirmation($order, $customer, $items, $paymentType = 'full') {
        $subject = "Confirmation de votre commande #{$order['order_number']}";
        if ($paymentType === 'deposit') {
            $subject = "Confirmation de paiement de l'acompte - Commande #{$order['order_number']}";
        } elseif ($paymentType === 'balance') {
            $subject = "Confirmation de paiement du solde - Commande #{$order['order_number']}";
        }

        $body = $this->getOrderConfirmationTemplate($order, $customer, $items, $paymentType);

        return $this->sendEmail($customer['email'], $subject, $body);
    }

    /**
     * Envoie une notification de nouvelle commande à l'admin
     */
    public function sendNewOrderNotificationToAdmin($order, $customer, $items, $paymentType = 'full') {
        $subject = "Nouvelle commande #{$order['order_number']} - {$customer['first_name']} {$customer['last_name']}";
        if ($paymentType === 'deposit') {
            $subject = "Acompte reçu pour la commande #{$order['order_number']} - {$customer['first_name']} {$customer['last_name']}";
        } elseif ($paymentType === 'balance') {
            $subject = "Solde reçu pour la commande #{$order['order_number']} - {$customer['first_name']} {$customer['last_name']}";
        }

        $body = $this->getAdminOrderNotificationTemplate($order, $customer, $items, $paymentType);

        return $this->sendEmail($this->adminEmail, $subject, $body);
    }

    /**
     * Envoie un email d'échec de paiement au client
     */
    public function sendPaymentFailedEmail($order, $customer) {
        $subject = "Échec du paiement - Commande #{$order['order_number']}";

        $body = $this->getPaymentFailedTemplate($order, $customer);

        return $this->sendEmail($customer['email'], $subject, $body);
    }

    /**
     * Template HTML pour confirmation de commande client
     */
    private function getOrderConfirmationTemplate($order, $customer, $items, $paymentType = 'full') {
        $totalOrder = $order['total_amount'] ?? $order['total'] ?? 0;
        $totalFormatted = number_format($totalOrder, 2, ',', ' ') . ' €';
        
        $amountPaid = $totalOrder;
        $paymentLabel = "Paiement intégral";
        $remainingHtml = "";

        // Fallback check: if paymentType is full but amount matches deposit
        if ($paymentType === 'full' && ($order['payment_strategy'] ?? '') === 'deposit' && ($order['deposit_payment_status'] ?? '') === 'paid') {
            // Check if we just paid the deposit (common if metadata was missing)
            // We can't know for sure which intent triggered this without metadata, 
            // but the webhook now forces the type.
        }

        if ($paymentType === 'deposit') {
            $amountPaid = $order['deposit_amount'] ?? 0;
            $paymentLabel = "Acompte (" . ($order['deposit_percentage'] ?? 0) . "%)";
            $remainingAmount = $order['remaining_amount'] ?? ($totalOrder - $amountPaid);
            $remainingHtml = "
                <p style='margin: 10px 0 0 0; color: #ef4444; font-size: 16px;'>
                    <strong>Reste à payer : " . number_format($remainingAmount, 2, ',', ' ') . " €</strong>
                </p>
            ";
        } elseif ($paymentType === 'balance') {
            $amountPaid = $order['remaining_amount'] ?? 0;
            $paymentLabel = "Solde restant";
        }

        $paidFormatted = number_format($amountPaid, 2, ',', ' ') . ' €';
        $orderDate = date('d/m/Y à H:i', strtotime($order['created_at']));

        $itemsHtml = '';
        foreach ($items as $item) {
            $price = $item['price'] ?? $item['unit_price'] ?? 0;
            $name = $item['name'] ?? $item['prompt'] ?? 'Article';
            $itemPrice = number_format($price * $item['quantity'], 2, ',', ' ') . ' €';
            $itemsHtml .= "
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb;'>{$name}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: center;'>{$item['quantity']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: right;'>{$itemPrice}</td>
                </tr>
            ";
        }

        // Ajouter les échantillons si présents
        require_once __DIR__ . '/../models/Order.php';
        $orderModel = new Order();
        $samples = $orderModel->getOrderSamples($order['id']);
        foreach ($samples as $sample) {
            $sampleName = "Échantillon : " . ($sample['sample_name'] ?? 'Échantillon') . " (" . ($sample['material'] ?? '') . ")";
            $samplePrice = number_format($sample['price'] * $sample['quantity'], 2, ',', ' ') . ' €';
            $itemsHtml .= "
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-style: italic;'>{$sampleName}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: center; color: #6b7280;'>{$sample['quantity']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: right; color: #6b7280;'>{$samplePrice}</td>
                </tr>
            ";
        }

        $statusText = "Nous avons bien reçu votre paiement et votre commande est maintenant confirmée.";
        if ($paymentType === 'deposit') {
            $statusText = "Nous avons bien reçu le paiement de votre acompte. Votre commande est maintenant confirmée et entrera prochainement en production.";
        } elseif ($paymentType === 'balance') {
            $statusText = "Nous avons bien reçu le paiement du solde de votre commande. Votre commande est maintenant intégralement payée.";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden;'>
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #d97706 0%, #b45309 100%); padding: 40px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 28px;'>{$this->siteName}</h1>
                                </td>
                            </tr>

                            <!-- Content -->
                            <tr>
                                <td style='padding: 40px;'>
                                    <h2 style='margin: 0 0 20px 0; color: #111827; font-size: 24px;'>
                                        Merci pour votre commande !
                                    </h2>

                                    <p style='margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;'>
                                        Bonjour {$customer['first_name']},
                                    </p>

                                    <p style='margin: 0 0 30px 0; color: #4b5563; font-size: 16px; line-height: 1.6;'>
                                        {$statusText}
                                        Nous allons la préparer dans les plus brefs délais.
                                    </p>

                                    <div style='background-color: #fef3c7; border-left: 4px solid #d97706; padding: 20px; margin-bottom: 30px; border-radius: 4px;'>
                                        <p style='margin: 0; color: #92400e; font-size: 16px; font-weight: bold;'>
                                            Type de paiement : {$paymentLabel}
                                        </p>
                                        <p style='margin: 5px 0 0 0; color: #92400e; font-size: 18px;'>
                                            Montant payé : <strong>{$paidFormatted}</strong>
                                        </p>
                                        {$remainingHtml}
                                    </div>

                                    <!-- Order Info -->
                                    <table width='100%' cellpadding='0' cellspacing='0' style='margin: 0 0 30px 0; background-color: #f9fafb; border-radius: 8px; padding: 20px;'>
                                        <tr>
                                            <td>
                                                <p style='margin: 0 0 10px 0; color: #6b7280; font-size: 14px;'>Numéro de commande</p>
                                                <p style='margin: 0 0 20px 0; color: #111827; font-size: 18px; font-weight: bold;'>#{$order['order_number']}</p>

                                                <p style='margin: 0 0 10px 0; color: #6b7280; font-size: 14px;'>Date de commande</p>
                                                <p style='margin: 0; color: #111827; font-size: 16px;'>{$orderDate}</p>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Items Table -->
                                    <h3 style='margin: 0 0 15px 0; color: #111827; font-size: 18px;'>Détails de la commande</h3>
                                    <table width='100%' cellpadding='0' cellspacing='0' style='margin: 0 0 30px 0; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;'>
                                        <thead>
                                            <tr style='background-color: #f9fafb;'>
                                                <th style='padding: 12px; text-align: left; color: #6b7280; font-size: 14px; font-weight: 600;'>Article</th>
                                                <th style='padding: 12px; text-align: center; color: #6b7280; font-size: 14px; font-weight: 600;'>Quantité</th>
                                                <th style='padding: 12px; text-align: right; color: #6b7280; font-size: 14px; font-weight: 600;'>Prix</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$itemsHtml}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan='2' style='padding: 16px; text-align: right; font-weight: bold; color: #111827; font-size: 18px;'>Total Commande</td>
                                                <td style='padding: 16px; text-align: right; font-weight: bold; color: #d97706; font-size: 18px;'>{$totalFormatted}</td>
                                            </tr>
                                        </tfoot>
                                    </table>

                                    <!-- Shipping Address -->
                                    <h3 style='margin: 0 0 15px 0; color: #111827; font-size: 18px;'>Adresse de livraison</h3>
                                    <div style='padding: 15px; background-color: #f9fafb; border-radius: 8px; margin: 0 0 30px 0;'>
                                        <p style='margin: 0; color: #4b5563; font-size: 14px; line-height: 1.6;'>
                                            {$customer['first_name']} {$customer['last_name']}<br>
                                            {$order['shipping_address']}
                                        </p>
                                    </div>

                                    <p style='margin: 0; color: #4b5563; font-size: 14px; line-height: 1.6;'>
                                        Vous recevrez un email avec le numéro de suivi dès que votre commande sera expédiée.
                                    </p>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #f9fafb; padding: 30px; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; color: #6b7280; font-size: 14px;'>
                                        Merci d'avoir choisi {$this->siteName}
                                    </p>
                                    <p style='margin: 0; color: #9ca3af; font-size: 12px;'>
                                        © " . date('Y') . " {$this->siteName}. Tous droits réservés.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Template HTML pour notification admin
     */
    private function getAdminOrderNotificationTemplate($order, $customer, $items, $paymentType = 'full') {
        $totalFormatted = number_format($order['total_amount'], 2, ',', ' ') . ' €';
        $orderDate = date('d/m/Y à H:i', strtotime($order['created_at']));

        $paymentInfo = "<strong>Paiement:</strong> Intégral";
        if ($paymentType === 'deposit') {
            $depositAmount = number_format($order['deposit_amount'] ?? 0, 2, ',', ' ') . ' €';
            $paymentInfo = "<strong>Paiement:</strong> ACOMPTE REÇU ({$depositAmount})";
        } elseif ($paymentType === 'balance') {
            $balanceAmount = number_format($order['remaining_amount'] ?? 0, 2, ',', ' ') . ' €';
            $paymentInfo = "<strong>Paiement:</strong> SOLDE REÇU ({$balanceAmount})";
        }

        $itemsList = '';
        foreach ($items as $item) {
            $itemName = $item['name'] ?? $item['prompt'] ?? 'Configuration personnalisée';
            $itemsList .= "• {$itemName} (x{$item['quantity']})\n";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px;'>
                            <tr>
                                <td style='background-color: #10b981; padding: 30px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 24px;'>Nouvelle commande reçue</h1>
                                </td>
                            </tr>

                            <tr>
                                <td style='padding: 30px;'>
                                    <h2 style='margin: 0 0 20px 0; color: #111827;'>Commande #{$order['order_number']}</h2>

                                    <p style='margin: 0 0 10px 0; color: #4b5563;'><strong>Client:</strong> {$customer['first_name']} {$customer['last_name']}</p>
                                    <p style='margin: 0 0 10px 0; color: #4b5563;'><strong>Email:</strong> {$customer['email']}</p>
                                    <p style='margin: 0 0 10px 0; color: #4b5563;'><strong>Téléphone:</strong> " . ($customer['phone'] ?? 'Non renseigné') . "</p>
                                    <p style='margin: 0 0 20px 0; color: #4b5563;'><strong>Date:</strong> {$orderDate}</p>

                                    <h3 style='margin: 0 0 10px 0; color: #111827;'>Articles commandés:</h3>
                                    <pre style='background-color: #f9fafb; padding: 15px; border-radius: 8px; margin: 0 0 20px 0;'>{$itemsList}</pre>

                                    <p style='margin: 0 0 20px 0; color: #111827; font-size: 18px;'><strong>Montant total: {$totalFormatted}</strong></p>
                                    <p style='margin: 0 0 20px 0; color: #111827; font-size: 18px;'>{$paymentInfo}</p>

                                    <h3 style='margin: 0 0 10px 0; color: #111827;'>Adresse de livraison:</h3>
                                    <p style='margin: 0; color: #4b5563;'>{$order['shipping_address']}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Template HTML pour échec de paiement
     */
    private function getPaymentFailedTemplate($order, $customer) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px;'>
                            <tr>
                                <td style='background-color: #ef4444; padding: 30px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 24px;'>Problème avec votre paiement</h1>
                                </td>
                            </tr>

                            <tr>
                                <td style='padding: 30px;'>
                                    <p style='margin: 0 0 20px 0; color: #4b5563; font-size: 16px;'>Bonjour {$customer['first_name']},</p>

                                    <p style='margin: 0 0 20px 0; color: #4b5563; font-size: 16px;'>
                                        Malheureusement, le paiement de votre commande #{$order['order_number']} n'a pas pu être traité.
                                    </p>

                                    <p style='margin: 0 0 20px 0; color: #4b5563; font-size: 16px;'>
                                        Raisons possibles:
                                    </p>
                                    <ul style='color: #4b5563; font-size: 16px;'>
                                        <li>Fonds insuffisants</li>
                                        <li>Carte expirée</li>
                                        <li>Informations de carte incorrectes</li>
                                        <li>Limitation bancaire</li>
                                    </ul>

                                    <p style='margin: 0 0 30px 0; color: #4b5563; font-size: 16px;'>
                                        Votre commande est toujours en attente. Vous pouvez réessayer le paiement ou nous contacter pour toute assistance.
                                    </p>

                                    <div style='text-align: center;'>
                                        <a href='http://localhost:3000/orders' style='display: inline-block; background-color: #d97706; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                                            Voir ma commande
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td style='background-color: #f9fafb; padding: 20px; text-align: center;'>
                                    <p style='margin: 0; color: #6b7280; font-size: 14px;'>
                                        Besoin d'aide ? Contactez-nous à {$this->adminEmail}
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Envoie un email avec le lien de paiement au client
     */
    public function sendPaymentLinkEmail($customerEmail, $customerName, $orderNumber, $paymentUrl, $expiresAt, $totalAmount) {
        $subject = "Lien de paiement pour votre commande #{$orderNumber}";

        $body = $this->getPaymentLinkTemplate($customerName, $orderNumber, $paymentUrl, $expiresAt, $totalAmount);

        return $this->sendEmail($customerEmail, $subject, $body);
    }

    /**
     * Template HTML pour email de lien de paiement
     */
    private function getPaymentLinkTemplate($customerName, $orderNumber, $paymentUrl, $expiresAt, $totalAmount) {
        $totalFormatted = number_format($totalAmount, 2, ',', ' ') . ' €';
        $expiryDate = date('d/m/Y à H:i', strtotime($expiresAt));

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: bold;'>
                                        {$this->siteName}
                                    </h1>
                                </td>
                            </tr>

                            <!-- Content -->
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <h2 style='color: #1f2937; margin: 0 0 20px 0; font-size: 24px;'>
                                        Bonjour {$customerName},
                                    </h2>

                                    <p style='color: #4b5563; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;'>
                                        Votre lien de paiement sécurisé est prêt ! Vous pouvez maintenant procéder au paiement de votre commande <strong>#{$orderNumber}</strong>.
                                    </p>

                                    <div style='background-color: #f9fafb; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px;'>
                                        <p style='margin: 0 0 10px 0; color: #1f2937; font-size: 14px;'>
                                            <strong>Montant total :</strong> {$totalFormatted}
                                        </p>
                                        <p style='margin: 0; color: #6b7280; font-size: 14px;'>
                                            <strong>Valable jusqu'au :</strong> {$expiryDate}
                                        </p>
                                    </div>

                                    <div style='text-align: center; margin: 30px 0;'>
                                        <a href='{$paymentUrl}' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: bold; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);'>
                                            Procéder au paiement
                                        </a>
                                    </div>

                                    <p style='color: #6b7280; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;'>
                                        Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :
                                    </p>
                                    <p style='color: #667eea; font-size: 14px; word-break: break-all; margin: 10px 0;'>
                                        {$paymentUrl}
                                    </p>

                                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;'>
                                        <p style='color: #6b7280; font-size: 14px; margin: 0;'>
                                            <strong>Sécurisé par Stripe</strong><br>
                                            Vos informations de paiement sont protégées et chiffrées.
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #f9fafb; padding: 20px 30px; text-align: center; border-top: 1px solid #e5e7eb;'>
                                    <p style='color: #9ca3af; font-size: 12px; margin: 0;'>
                                        Cet email a été envoyé par {$this->siteName}<br>
                                        Si vous n'avez pas demandé ce lien, vous pouvez ignorer cet email.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail($to, $name, $resetUrl) {
        $subject = "Réinitialisation de votre mot de passe - ArchiMeuble";
        $body = $this->getPasswordResetTemplate($name, $resetUrl);
        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Template pour réinitialisation de mot de passe
     */
    private function getPasswordResetTemplate($name, $resetUrl) {
        $year = date('Y');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #1A1917; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
                .header { text-align: center; margin-bottom: 40px; }
                .logo { font-size: 24px; font-weight: bold; text-decoration: none; color: #1A1917; }
                .content { background-color: #FAFAF9; padding: 40px; border: 1px solid #E8E6E3; }
                .button { display: inline-block; padding: 16px 32px; background-color: #1A1917; color: #FFFFFF !important; text-decoration: none; font-weight: 600; margin-top: 30px; }
                .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #706F6C; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <span class='logo'>ArchiMeuble</span>
                </div>
                <div class='content'>
                    <h2 style='margin-top: 0;'>Bonjour {$name},</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte ArchiMeuble.</p>
                    <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien est valable pendant 1 heure.</p>
                    <div style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>Réinitialiser mon mot de passe</a>
                    </div>
                    <p style='margin-top: 30px; font-size: 14px; color: #706F6C;'>
                        Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité. 
                        Votre mot de passe restera inchangé.
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; {$year} ArchiMeuble. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Envoie un email d'annulation de commande
     */
    public function sendOrderCancelledEmail($to, $name, $orderNumber) {
        $subject = "Annulation de votre commande #{$orderNumber} - ArchiMeuble";
        $body = $this->getOrderCancelledTemplate($name, $orderNumber);
        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Template pour annulation de commande
     */
    private function getOrderCancelledTemplate($name, $orderNumber) {
        $year = date('Y');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #1A1917; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
                .header { text-align: center; margin-bottom: 40px; }
                .logo { font-size: 24px; font-weight: bold; text-decoration: none; color: #1A1917; }
                .content { background-color: #FAFAF9; padding: 40px; border: 1px solid #E8E6E3; }
                .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #706F6C; }
                .status-badge { display: inline-block; padding: 4px 12px; background-color: #FEE2E2; color: #B91C1C; font-size: 12px; font-weight: bold; border-radius: 9999px; text-transform: uppercase; margin-bottom: 16px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <span class='logo'>ArchiMeuble</span>
                </div>
                <div class='content'>
                    <div class='status-badge'>Annulée</div>
                    <h2 style='margin-top: 0;'>Bonjour {$name},</h2>
                    <p>Nous vous informons que votre commande <strong>#{$orderNumber}</strong> a été annulée.</p>
                    <p>Si vous aviez des liens de paiement en attente pour cette commande, ils ont été désactivés par mesure de sécurité.</p>
                    <p>Si vous avez des questions concernant cette annulation ou si vous souhaitez passer une nouvelle commande, notre équipe reste à votre entière disposition.</p>
                    <p style='margin-top: 30px; font-size: 14px; color: #706F6C;'>
                        Cordialement,<br>
                        L'équipe ArchiMeuble
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; {$year} ArchiMeuble. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function sendEmail($to, $subject, $htmlBody) {
        $apiKey = getenv('RESEND_API_KEY');
        $from = 'contact@archimeuble.com'; // Domaine vérifié sur Resend !
        
        error_log("EmailService: Attempting to send email to {$to} via Resend API (from {$from})");

        $data = [
            'from' => "ArchiMeuble <{$from}>",
            'to' => [$to],
            'subject' => $subject,
            'html' => $htmlBody,
        ];

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("EmailService: Email sent successfully via Resend API to {$to}");
            return true;
        } else {
            error_log("EmailService ERROR: Resend API failed with code {$httpCode}. Response: {$response}. Curl Error: {$error}");
            return false;
        }
    }
}
