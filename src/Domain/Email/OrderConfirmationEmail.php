<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class OrderConfirmationEmail
{
    public static function subject(string $orderNumber, string $paymentType): string
    {
        return match ($paymentType) {
            'deposit' => "Confirmation de paiement de l'acompte - Commande #{$orderNumber}",
            'balance' => "Confirmation de paiement du solde - Commande #{$orderNumber}",
            default => "Confirmation de votre commande #{$orderNumber}",
        };
    }

    public static function html(array $order, array $customer, array $itemRows, array $sampleRows, string $paymentType, string $frontendUrl): string
    {
        $year = date('Y');
        $totalOrder = (float) ($order['total_amount'] ?? $order['total'] ?? 0);
        $totalFormatted = number_format($totalOrder, 2, ',', ' ') . ' €';
        [$amountPaid, $paymentLabel, $remainingHtml] = OrderConfirmationEmailParts::paymentBreakdown($order, $paymentType, $totalOrder);
        $paidFormatted = number_format($amountPaid, 2, ',', ' ') . ' €';
        $createdTimestamp = strtotime((string) $order['created_at']);
        $orderDate = date('d/m/Y à H:i', $createdTimestamp === false ? time() : $createdTimestamp);
        $itemsHtml = OrderConfirmationEmailParts::itemsRows($itemRows) . OrderConfirmationEmailParts::sampleRows($sampleRows);
        $statusText = match ($paymentType) {
            'deposit' => 'Nous avons bien reçu le paiement de votre acompte. Votre commande est maintenant confirmée et entrera prochainement en production.',
            'balance' => 'Nous avons bien reçu le paiement du solde de votre commande. Votre commande est maintenant intégralement payée.',
            default => 'Nous avons bien reçu votre paiement et votre commande est maintenant confirmée.',
        };
        $siteUrl = $frontendUrl;
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #1A1917; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
                .header { text-align: center; margin-bottom: 40px; }
                .logo { font-size: 24px; font-weight: bold; text-decoration: none; color: #1A1917; }
                .content { background-color: #FAFAF9; padding: 40px; border: 1px solid #E8E6E3; }
                .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #706F6C; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <span class='logo'>ArchiMeuble</span>
                </div>
                <div class='content'>
                    <div style='display: inline-block; padding: 6px 16px; background-color: #D1FAE5; color: #065F46; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 20px;'>
                        Paiement confirmé
                    </div>

                    <h2 style='margin-top: 0;'>Bonjour {$customer['first_name']},</h2>

                    <p>{$statusText}</p>

                    <!-- Récapitulatif paiement -->
                    <div style='background-color: #FFFFFF; padding: 24px; border: 1px solid #E8E6E3; margin: 24px 0;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Commande</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>#{$order['order_number']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Date</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$orderDate}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Type de paiement</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$paymentLabel}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Montant payé</td>
                                <td style='padding: 10px 0; color: #065F46; text-align: right; font-weight: 700; font-size: 16px;'>{$paidFormatted}</td>
                            </tr>
                            {$remainingHtml}
                        </table>
                    </div>

                    <!-- Articles -->
                    <h3 style='margin: 24px 0 16px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; color: #1A1917;'>Articles commandés</h3>
                    <div style='background-color: #FFFFFF; padding: 24px; border: 1px solid #E8E6E3;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #706F6C; font-size: 12px; font-weight: 600; text-transform: uppercase; border-bottom: 1px solid #E8E6E3;'>Article</td>
                                <td style='padding: 8px 0; color: #706F6C; font-size: 12px; font-weight: 600; text-transform: uppercase; text-align: center; border-bottom: 1px solid #E8E6E3;'>Qté</td>
                                <td style='padding: 8px 0; color: #706F6C; font-size: 12px; font-weight: 600; text-transform: uppercase; text-align: right; border-bottom: 1px solid #E8E6E3;'>Prix</td>
                            </tr>
                            {$itemsHtml}
                            <tr>
                                <td colspan='2' style='padding: 12px 0; text-align: right; font-weight: 700; color: #1A1917; font-size: 16px;'>Total</td>
                                <td style='padding: 12px 0; text-align: right; font-weight: 700; color: #8B7355; font-size: 16px;'>{$totalFormatted}</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Adresse de livraison -->
                    <h3 style='margin: 24px 0 16px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; color: #1A1917;'>Adresse de livraison</h3>
                    <div style='background-color: #FFFFFF; padding: 24px; border: 1px solid #E8E6E3;'>
                        <p style='margin: 0; color: #1A1917; font-size: 14px; line-height: 1.6;'>
                            {$customer['first_name']} {$customer['last_name']}<br>
                            {$order['shipping_address']}
                        </p>
                    </div>

                    <div style='text-align: center; margin-top: 32px;'>
                        <a href='{$siteUrl}/account?section=orders' style='display: inline-block; padding: 14px 32px; background-color: #1A1917; color: #FFFFFF; text-decoration: none; font-weight: 600;'>
                            Suivre ma commande
                        </a>
                    </div>

                    <p style='margin-top: 30px; font-size: 14px; color: #706F6C;'>
                        Si vous avez des questions, n'hésitez pas à nous contacter à <a href='mailto:pro.archimeuble@gmail.com' style='color: #8B7355;'>pro.archimeuble@gmail.com</a>
                    </p>
                    <p style='font-size: 14px; color: #706F6C;'>
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
}
