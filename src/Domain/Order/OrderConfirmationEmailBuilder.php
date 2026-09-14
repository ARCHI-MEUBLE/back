<?php

declare(strict_types=1);

namespace App\Domain\Order;

final class OrderConfirmationEmailBuilder
{
    public static function build(array $order, array $items, array $samples, array $catalogueItems, array $facadeItems): array
    {
        $hasConfigurations = $items !== [];
        $hasCatalogueItems = $catalogueItems !== [];
        $hasFacades = $facadeItems !== [];
        $hasSamples = $samples !== [];
        $total = (float) ($order['total_amount'] ?? $order['total'] ?? 0);
        $isSamplesOnly = $hasSamples && !$hasConfigurations && !$hasCatalogueItems && !$hasFacades;
        $itemsHtml = OrderConfirmationSections::configurations($items)
            . OrderConfirmationSections::facades($facadeItems)
            . OrderConfirmationSections::catalogue($catalogueItems)
            . OrderConfirmationSections::samples($samples);
        [$subject, $intro] = self::subjectAndIntro($order['order_number'], $isSamplesOnly, $hasConfigurations, $hasFacades);
        $totalFormatted = $total === 0.0 ? 'Gratuit' : number_format($total, 2, ',', ' ') . ' €';
        $envFrontendUrl = getenv('FRONTEND_URL');
        $frontendUrl = is_string($envFrontendUrl) && $envFrontendUrl !== '' ? $envFrontendUrl : 'http://localhost:3000';
        return [$subject, self::envelope($intro, $order, $itemsHtml, $totalFormatted, $frontendUrl)];
    }

    private static function subjectAndIntro(string $orderNumber, bool $isSamplesOnly, bool $hasConfigurations, bool $hasFacades): array
    {
        if ($isSamplesOnly) {
            return ["Vos échantillons sont en préparation - #{$orderNumber}", 'Vos échantillons ont bien été commandés et seront expédiés sous 24-48h.'];
        }
        if ($hasConfigurations) {
            return ["Confirmation de commande #{$orderNumber}", 'Votre commande a été confirmée. Nous allons commencer la fabrication de vos meubles sur mesure.'];
        }
        if ($hasFacades) {
            return ["Confirmation de commande #{$orderNumber}", 'Votre commande a été confirmée. Vos façades sur mesure vont être fabriquées.'];
        }
        return ["Confirmation de commande #{$orderNumber}", 'Votre commande a été confirmée. Vos articles vont être préparés pour l\'expédition.'];
    }

    private static function envelope(string $intro, array $order, string $itemsHtml, string $totalFormatted, string $frontendUrl): string
    {
        return '
    <div style="font-family: Georgia, serif; max-width: 600px; margin: 0 auto; padding: 40px 20px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #1A1917; font-size: 28px; margin: 0;">Merci pour votre commande !</h1>
            <p style="color: #706F6C; margin-top: 10px;">' . htmlspecialchars($intro) . '</p>
        </div>
        <div style="background: #F5F5F4; padding: 20px; margin-bottom: 30px;">
            <p style="margin: 0; color: #706F6C; font-size: 12px; text-transform: uppercase;">Numéro de commande</p>
            <p style="margin: 5px 0 0 0; color: #1A1917; font-size: 20px; font-weight: bold;">' . htmlspecialchars($order['order_number']) . '</p>
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
            <p style="color: #706F6C; margin: 0;">' . nl2br(htmlspecialchars($order['shipping_address'])) . '</p>
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
    }
}
