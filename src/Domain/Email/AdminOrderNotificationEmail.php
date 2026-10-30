<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class AdminOrderNotificationEmail
{
    public static function subject(string $orderNumber, string $firstName, string $lastName, string $paymentType): string
    {
        return match ($paymentType) {
            'deposit' => "Acompte reçu pour la commande #{$orderNumber} - {$firstName} {$lastName}",
            'balance' => "Solde reçu pour la commande #{$orderNumber} - {$firstName} {$lastName}",
            default => "Nouvelle commande #{$orderNumber} - {$firstName} {$lastName}",
        };
    }

    public static function html(array $order, array $customer, array $itemNames, string $paymentType): string
    {
        $totalFormatted = number_format((float) $order['total_amount'], 2, ',', ' ') . ' €';
        $createdTimestamp = strtotime((string) $order['created_at']);
        $orderDate = date('d/m/Y à H:i', $createdTimestamp === false ? time() : $createdTimestamp);
        $paymentInfo = match ($paymentType) {
            'deposit' => '<strong>Paiement:</strong> ACOMPTE REÇU (' . number_format((float) ($order['deposit_amount'] ?? 0), 2, ',', ' ') . ' €)',
            'balance' => '<strong>Paiement:</strong> SOLDE REÇU (' . number_format((float) ($order['remaining_amount'] ?? 0), 2, ',', ' ') . ' €)',
            default => '<strong>Paiement:</strong> Intégral',
        };
        $itemsList = '';
        foreach ($itemNames as $entry) {
            $itemsList .= "• {$entry['name']} (x{$entry['quantity']})\n";
        }
        $phone = ($customer['phone'] ?? '') !== '' ? $customer['phone'] : 'Non renseigné';
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
                                    <p style='margin: 0 0 10px 0; color: #4b5563;'><strong>Téléphone:</strong> {$phone}</p>
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
}
