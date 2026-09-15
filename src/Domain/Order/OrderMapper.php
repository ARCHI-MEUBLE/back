<?php

declare(strict_types=1);

namespace App\Domain\Order;

final class OrderMapper
{
    public static function toFrontend(array $order): array
    {
        $formatted = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'status' => $order['status'],
            'total' => $order['total_amount'] ?? $order['total'] ?? 0,
            'amount' => $order['total_amount'] ?? $order['total'] ?? 0,
            'shipping_address' => $order['shipping_address'] ?? '',
            'billing_address' => $order['billing_address'] ?? '',
            'payment_method' => $order['payment_method'] ?? 'card',
            'payment_status' => $order['payment_status'] ?? 'pending',
            'payment_strategy' => $order['payment_strategy'] ?? 'full',
            'deposit_percentage' => $order['deposit_percentage'] ?? 0,
            'deposit_amount' => $order['deposit_amount'] ?? 0,
            'remaining_amount' => $order['remaining_amount'] ?? 0,
            'deposit_payment_status' => $order['deposit_payment_status'] ?? 'pending',
            'balance_payment_status' => $order['balance_payment_status'] ?? 'pending',
            'notes' => $order['notes'] ?? '',
            'admin_notes' => $order['admin_notes'] ?? '',
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'] ?? $order['created_at'],
        ];
        if (isset($order['customer'])) {
            $formatted['customer'] = $order['customer'];
            $formatted['customer_name'] = trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''));
            $formatted['customer_email'] = $order['customer']['email'] ?? '';
            $formatted['customer_phone'] = $order['customer']['phone'] ?? '';
        } elseif (isset($order['customer_email'])) {
            $formatted['customer_name'] = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
            $formatted['customer_email'] = $order['customer_email'];
        }
        if (isset($order['items'])) {
            $formatted['items'] = array_map(static function (array $item): array {
                if (isset($item['config_data']) && is_string($item['config_data'])) {
                    $configData = json_decode($item['config_data'], true);
                    $item['name'] = $configData['name'] ?? 'Configuration sans nom';
                    $item['config_data'] = $configData;
                }
                if (!isset($item['price']) && isset($item['unit_price'])) {
                    $item['price'] = $item['unit_price'];
                }
                return $item;
            }, $order['items']);
        }
        if (isset($order['catalogue_items'])) {
            $formatted['catalogue_items'] = $order['catalogue_items'];
        }
        if (isset($order['facade_items'])) {
            $formatted['facade_items'] = array_map(static function (array $facade): array {
                if (isset($facade['config_data']) && is_string($facade['config_data'])) {
                    $facade['config'] = json_decode($facade['config_data'], true);
                }
                return $facade;
            }, $order['facade_items']);
        }
        if (isset($order['samples'])) {
            $formatted['samples'] = $order['samples'];
        }
        return $formatted;
    }
}
