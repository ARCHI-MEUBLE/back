<?php

declare(strict_types=1);

namespace App\Domain\Order;

final class OrderLines
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function all(int $orderId): array
    {
        return array_merge(
            self::configurationRows($this->orders->itemsFor($orderId)),
            $this->extraRows($orderId),
            self::sampleRows($this->orders->samplesFor($orderId)),
        );
    }

    public function extraRows(int $orderId): array
    {
        return array_merge(
            self::facadeRows($this->orders->facadeItemsFor($orderId)),
            self::catalogueRows($this->orders->catalogueItemsFor($orderId)),
        );
    }

    public static function configurationRows(array $items): array
    {
        return array_map(static function (array $item): array {
            $config = is_string($item['config_data'] ?? null) ? json_decode($item['config_data'], true) : ($item['config_data'] ?? null);
            $name = is_array($config) && is_string($config['name'] ?? null) && $config['name'] !== '' ? $config['name'] : 'Meuble sur mesure';
            return self::row($name . self::installationSuffix($config), $item);
        }, $items);
    }

    public static function facadeRows(array $facadeItems): array
    {
        return array_map(static function (array $facade): array {
            $config = is_string($facade['config_data'] ?? null) ? json_decode($facade['config_data'], true) : ($facade['config_data'] ?? null);
            $config = is_array($config) ? $config : [];
            $width = is_numeric($config['width'] ?? null) ? (float) $config['width'] / 10 : 0;
            $height = is_numeric($config['height'] ?? null) ? (float) $config['height'] / 10 : 0;
            $material = is_array($config['material'] ?? null) && is_string($config['material']['name'] ?? null) ? ' · ' . $config['material']['name'] : '';
            return self::row('Façade ' . $width . ' × ' . $height . ' cm' . $material, $facade);
        }, $facadeItems);
    }

    public static function catalogueRows(array $catalogueItems): array
    {
        return array_map(static function (array $item): array {
            $name = (string) ($item['product_name'] ?? $item['name'] ?? $item['item_name'] ?? 'Article');
            $variation = is_string($item['variation_name'] ?? null) && $item['variation_name'] !== '' ? ' · ' . $item['variation_name'] : '';
            return self::row($name . $variation, $item);
        }, $catalogueItems);
    }

    public static function sampleRows(array $samples): array
    {
        return array_map(static function (array $sample): array {
            $material = is_string($sample['material'] ?? null) && $sample['material'] !== '' ? ' (' . $sample['material'] . ')' : '';
            return self::row('Échantillon : ' . ($sample['sample_name'] ?? 'Échantillon') . $material, ['unit_price' => $sample['price'] ?? 0] + $sample);
        }, $samples);
    }

    public static function installationSuffix(mixed $config): string
    {
        return is_array($config) && is_array($config['features'] ?? null) && ($config['features']['installation'] ?? false) === true
            ? ' · pose et montage compris'
            : '';
    }

    private static function row(string $name, array $item): array
    {
        $quantity = (int) ($item['quantity'] ?? 1);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        return [
            'display_name' => $name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => isset($item['total_price']) ? (float) $item['total_price'] : round($unitPrice * $quantity, 2),
        ];
    }
}
