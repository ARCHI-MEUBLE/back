<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class OrderCancelledEmail
{
    public static function subject(string $orderNumber): string
    {
        return "Annulation de votre commande #{$orderNumber} - ArchiMeuble";
    }

    public static function html(string $name, string $orderNumber): string
    {
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
}
