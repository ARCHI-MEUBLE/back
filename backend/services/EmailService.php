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
        $this->from = getenv('SMTP_FROM_EMAIL') ?: 'noreply@archimeuble.com';
        $this->adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@archimeuble.com';
    }

    /**
     * Envoie un email de confirmation de commande au client
     */
    public function sendOrderConfirmation($order, $customer, $items) {
        $subject = "Confirmation de votre commande #{$order['order_number']}";

        $body = $this->getOrderConfirmationTemplate($order, $customer, $items);

        return $this->sendEmail($customer['email'], $subject, $body);
    }

    /**
     * Envoie une notification de nouvelle commande à l'admin
     */
    public function sendNewOrderNotificationToAdmin($order, $customer, $items) {
        $subject = "Nouvelle commande #{$order['order_number']} - {$customer['first_name']} {$customer['last_name']}";

        $body = $this->getAdminOrderNotificationTemplate($order, $customer, $items);

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
    private function getOrderConfirmationTemplate($order, $customer, $items) {
        $totalFormatted = number_format($order['total_amount'], 2, ',', ' ') . ' €';
        $orderDate = date('d/m/Y à H:i', strtotime($order['created_at']));

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemPrice = number_format($item['price'] * $item['quantity'], 2, ',', ' ') . ' €';
            $itemsHtml .= "
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb;'>{$item['name']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: center;'>{$item['quantity']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: right;'>{$itemPrice}</td>
                </tr>
            ";
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
                                        Nous avons bien reçu votre paiement et votre commande est maintenant confirmée.
                                        Nous allons la préparer dans les plus brefs délais.
                                    </p>

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
                                                <td colspan='2' style='padding: 16px; text-align: right; font-weight: bold; color: #111827; font-size: 18px;'>Total</td>
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
    private function getAdminOrderNotificationTemplate($order, $customer, $items) {
        $totalFormatted = number_format($order['total_amount'], 2, ',', ' ') . ' €';
        $orderDate = date('d/m/Y à H:i', strtotime($order['created_at']));

        $itemsList = '';
        foreach ($items as $item) {
            $itemsList .= "• {$item['name']} (x{$item['quantity']})\n";
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
                                    <p style='margin: 0 0 10px 0; color: #4b5563;'><strong>Téléphone:</strong> {$customer['phone']}</p>
                                    <p style='margin: 0 0 20px 0; color: #4b5563;'><strong>Date:</strong> {$orderDate}</p>

                                    <h3 style='margin: 0 0 10px 0; color: #111827;'>Articles commandés:</h3>
                                    <pre style='background-color: #f9fafb; padding: 15px; border-radius: 8px; margin: 0 0 20px 0;'>{$itemsList}</pre>

                                    <p style='margin: 0 0 20px 0; color: #111827; font-size: 18px;'><strong>Montant total: {$totalFormatted}</strong></p>

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
     * Envoie un email via SMTP Gmail
     */
    private function sendEmail($to, $subject, $htmlBody) {
        // Utiliser le SMTPMailer de Calendly qui fonctionne déjà
        require_once __DIR__ . '/../api/calendly/SMTPMailer.php';

        // Récupérer config SMTP depuis .env
        $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $smtpPort = getenv('SMTP_PORT') ?: 587;
        $smtpUser = getenv('SMTP_USERNAME') ?: getenv('SMTP_FROM_EMAIL');
        $smtpPass = getenv('SMTP_PASSWORD');

        if (!$smtpUser || !$smtpPass) {
            error_log("SMTP credentials not configured");
            return false;
        }

        try {
            $mailer = new SMTPMailer(
                $smtpHost,
                $smtpPort,
                $smtpUser,
                $smtpPass,
                $this->from,
                $this->siteName
            );

            $success = $mailer->send($to, $subject, $htmlBody);

            if ($success) {
                error_log("Email sent to {$to}: {$subject}");
            } else {
                error_log("Failed to send email to {$to}: {$subject}");
            }

            return $success;

        } catch (Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            return false;
        }
    }
}
