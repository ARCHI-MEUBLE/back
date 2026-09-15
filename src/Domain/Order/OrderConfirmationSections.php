<?php

declare(strict_types=1);

namespace App\Domain\Order;

final class OrderConfirmationSections
{
    public static function configurations(array $items): string
    {
        if ($items === []) {
            return '';
        }
        $html = '<h4 style="margin: 20px 0 10px 0; color: #706F6C; font-size: 12px; text-transform: uppercase;">Meubles sur mesure</h4>';
        foreach ($items as $item) {
            $configData = is_string($item['config_data']) ? json_decode($item['config_data'], true) : $item['config_data'];
            $name = $configData['name'] ?? $item['name'] ?? 'Configuration';
            $total = $item['total_price'] ?? ($item['unit_price'] * $item['quantity']);
            $html .= self::row(htmlspecialchars((string) $name), 'Qté: ' . $item['quantity'], number_format((float) $total, 2, ',', ' ') . ' €');
        }
        return $html;
    }

    public static function facades(array $facadeItems): string
    {
        if ($facadeItems === []) {
            return '';
        }
        $html = '<h4 style="margin: 20px 0 10px 0; color: #706F6C; font-size: 12px; text-transform: uppercase;">Façades sur mesure</h4>';
        foreach ($facadeItems as $facade) {
            $config = is_string($facade['config_data']) ? json_decode($facade['config_data'], true) : $facade['config_data'];
            $material = $config['material']['name'] ?? 'Matériau';
            $width = isset($config['width']) ? ($config['width'] / 10) : 0;
            $height = isset($config['height']) ? ($config['height'] / 10) : 0;
            $depth = $config['depth'] ?? 19;
            $total = $facade['total_price'] ?? ($facade['unit_price'] * $facade['quantity']);
            $label = 'Façade ' . $width . ' × ' . $height . ' cm · ' . $depth . ' mm';
            $html .= self::row($label, htmlspecialchars((string) $material) . ' · Qté: ' . $facade['quantity'], number_format((float) $total, 2, ',', ' ') . ' €');
        }
        return $html;
    }

    public static function catalogue(array $catalogueItems): string
    {
        if ($catalogueItems === []) {
            return '';
        }
        $html = '<h4 style="margin: 20px 0 10px 0; color: #706F6C; font-size: 12px; text-transform: uppercase;">Articles boutique</h4>';
        foreach ($catalogueItems as $item) {
            $name = $item['name'] ?? $item['item_name'] ?? 'Article';
            $total = $item['total_price'] ?? ($item['unit_price'] * $item['quantity']);
            $html .= '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #E8E6E3;">';
            $html .= '<div><strong>' . htmlspecialchars((string) $name) . '</strong>';
            if (isset($item['variation_name']) && $item['variation_name'] !== '') {
                $html .= '<br><span style="color: #706F6C; font-size: 14px;">' . htmlspecialchars((string) $item['variation_name']) . '</span>';
            }
            $html .= '<br><span style="color: #706F6C; font-size: 14px;">Qté: ' . $item['quantity'] . '</span></div>';
            $html .= '<div style="text-align: right;"><strong>' . number_format((float) $total, 2, ',', ' ') . ' €</strong></div></div>';
        }
        return $html;
    }

    public static function samples(array $samples): string
    {
        if ($samples === []) {
            return '';
        }
        $total = 0.0;
        foreach ($samples as $sample) {
            $total += ((float) ($sample['price'] ?? 0)) * $sample['quantity'];
        }
        $free = $total === 0.0;
        $html = '<h4 style="margin: 20px 0 10px 0; color: ' . ($free ? '#059669' : '#706F6C') . '; font-size: 12px; text-transform: uppercase;">' . ($free ? 'Échantillons gratuits' : 'Échantillons') . '</h4>';
        foreach ($samples as $sample) {
            $price = ((float) ($sample['price'] ?? 0)) * $sample['quantity'];
            $display = $price === 0.0 ? '<strong style="color: #059669;">Gratuit</strong>' : '<strong>' . number_format($price, 2, ',', ' ') . ' €</strong>';
            $sub = htmlspecialchars((string) ($sample['material'] ?? '')) . ' · Qté: ' . $sample['quantity'];
            $html .= '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #E8E6E3;"><div><strong>' . htmlspecialchars((string) ($sample['sample_name'] ?? 'Échantillon')) . '</strong><br><span style="color: #706F6C; font-size: 14px;">' . $sub . '</span></div><div style="text-align: right;">' . $display . '</div></div>';
        }
        return $html;
    }

    private static function row(string $title, string $subtitleHtml, string $amount): string
    {
        return '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #E8E6E3;">'
            . '<div><strong>' . $title . '</strong><br><span style="color: #706F6C; font-size: 14px;">' . $subtitleHtml . '</span></div>'
            . '<div style="text-align: right;"><strong>' . $amount . '</strong></div></div>';
    }
}
