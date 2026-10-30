<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class OrderConfirmationEmailParts
{
    public static function paymentBreakdown(array $order, string $paymentType, float $totalOrder): array
    {
        if ($paymentType === 'deposit') {
            $amountPaid = (float) ($order['deposit_amount'] ?? 0);
            $paymentLabel = 'Acompte (' . ($order['deposit_percentage'] ?? 0) . '%)';
            $remainingAmount = (float) ($order['remaining_amount'] ?? ($totalOrder - $amountPaid));
            $remainingHtml = "
                <tr>
                    <td style='padding: 10px 0; color: #706F6C; font-size: 14px;'>Reste à payer</td>
                    <td style='padding: 10px 0; color: #B91C1C; text-align: right; font-weight: 600;'>" . number_format($remainingAmount, 2, ',', ' ') . ' €</td>
                </tr>
            ';
            return [$amountPaid, $paymentLabel, $remainingHtml];
        }
        if ($paymentType === 'balance') {
            return [(float) ($order['remaining_amount'] ?? 0), 'Solde restant', ''];
        }
        return [$totalOrder, 'Paiement intégral', ''];
    }

    public static function itemsRows(array $itemRows): string
    {
        $html = '';
        foreach ($itemRows as $item) {
            $itemPrice = number_format($item['price'] * $item['quantity'], 2, ',', ' ') . ' €';
            $html .= "
                <tr>
                    <td style='padding: 10px 0; color: #1A1917; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>{$item['name']}</td>
                    <td style='padding: 10px 0; color: #1A1917; text-align: center; border-bottom: 1px solid #F0EFEA;'>{$item['quantity']}</td>
                    <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600; border-bottom: 1px solid #F0EFEA;'>{$itemPrice}</td>
                </tr>
            ";
        }
        return $html;
    }

    public static function sampleRows(array $sampleRows): string
    {
        $html = '';
        foreach ($sampleRows as $sample) {
            $sampleName = 'Échantillon : ' . ($sample['sample_name'] ?? 'Échantillon') . ' (' . ($sample['material'] ?? '') . ')';
            $samplePrice = number_format($sample['price'] * $sample['quantity'], 2, ',', ' ') . ' €';
            $html .= "
                <tr>
                    <td style='padding: 10px 0; color: #706F6C; font-size: 14px; font-style: italic; border-bottom: 1px solid #F0EFEA;'>{$sampleName}</td>
                    <td style='padding: 10px 0; color: #706F6C; text-align: center; border-bottom: 1px solid #F0EFEA;'>{$sample['quantity']}</td>
                    <td style='padding: 10px 0; color: #706F6C; text-align: right; border-bottom: 1px solid #F0EFEA;'>{$samplePrice}</td>
                </tr>
            ";
        }
        return $html;
    }
}
