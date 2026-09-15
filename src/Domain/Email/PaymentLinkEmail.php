<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class PaymentLinkEmail
{
    public static function subject(string $orderNumber): string
    {
        return "Lien de paiement pour votre commande #{$orderNumber}";
    }

    public static function html(string $customerName, string $orderNumber, string $paymentUrl, string $expiresAt, float $totalAmount): string
    {
        $year = date('Y');
        $totalFormatted = number_format($totalAmount, 2, ',', ' ') . ' €';
        $expiryTimestamp = strtotime($expiresAt);
        $expiryDate = date('d/m/Y à H:i', $expiryTimestamp === false ? time() : $expiryTimestamp);
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
                    <h2 style='margin-top: 0;'>Bonjour {$customerName},</h2>

                    <p>Votre lien de paiement sécurisé est prêt pour la commande <strong>#{$orderNumber}</strong>.</p>

                    <!-- Détails -->
                    <div style='background-color: #FFFFFF; padding: 24px; border: 1px solid #E8E6E3; margin: 24px 0;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Commande</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>#{$orderNumber}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Montant à régler</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 700; font-size: 16px;'>{$totalFormatted}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px;'>Valable jusqu'au</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$expiryDate}</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Bouton -->
                    <div style='text-align: center; margin: 32px 0;'>
                        <a href='{$paymentUrl}' style='display: inline-block; padding: 16px 40px; background-color: #1A1917; color: #FFFFFF; text-decoration: none; font-weight: 600; font-size: 15px;'>
                            Procéder au paiement
                        </a>
                    </div>

                    <p style='font-size: 13px; color: #706F6C;'>
                        Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :
                    </p>
                    <p style='font-size: 13px; color: #8B7355; word-break: break-all;'>
                        {$paymentUrl}
                    </p>

                    <div style='margin-top: 24px; padding-top: 20px; border-top: 1px solid #E8E6E3;'>
                        <p style='font-size: 13px; color: #706F6C; margin: 0;'>
                            Paiement sécurisé par Stripe. Vos informations bancaires sont protégées et chiffrées.
                        </p>
                    </div>

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
}
