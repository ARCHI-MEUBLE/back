<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class PaymentFailedEmail
{
    public static function subject(string $orderNumber): string
    {
        return "Échec du paiement - Commande #{$orderNumber}";
    }

    public static function html(string $firstName, string $orderNumber, string $adminEmail): string
    {
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
                                    <p style='margin: 0 0 20px 0; color: #4b5563; font-size: 16px;'>Bonjour {$firstName},</p>

                                    <p style='margin: 0 0 20px 0; color: #4b5563; font-size: 16px;'>
                                        Malheureusement, le paiement de votre commande #{$orderNumber} n'a pas pu être traité.
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
                                        <a href='http://127.0.0.1:3000/orders' style='display: inline-block; background-color: #d97706; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                                            Voir ma commande
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td style='background-color: #f9fafb; padding: 20px; text-align: center;'>
                                    <p style='margin: 0; color: #6b7280; font-size: 14px;'>
                                        Besoin d'aide ? Contactez-nous à {$adminEmail}
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
}
