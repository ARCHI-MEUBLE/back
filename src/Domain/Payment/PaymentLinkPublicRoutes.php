<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class PaymentLinkPublicRoutes
{
    public function __construct(private readonly PaymentLinkRepository $links) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('payment-link/index', errorStyle: ErrorStyle::Success)->get(null, fn(Request $r): Response => $this->show($r));
    }

    private function show(Request $r): Response
    {
        $token = trim($r->queryString('token') ?? '');
        if ($token === '') {
            throw new DomainException('Token manquant');
        }
        $validation = $this->links->validateLink($token);
        if ($validation['valid'] !== true) {
            $message = $validation['message'];
            return Response::json([
                'success' => false,
                'error' => $message,
                'expired' => str_contains($message, 'expiré'),
                'used' => str_contains($message, 'utilisé'),
                'revoked' => str_contains($message, 'révoqué'),
            ], 400);
        }
        $this->links->markAsAccessed($token);
        $orderData = $this->links->getOrderItemsByToken($token);
        if ($orderData === null) {
            throw new NotFoundException('Commande introuvable');
        }
        $order = $orderData['order'];
        return Response::json(['success' => true, 'data' => [
            'order' => [
                'order_number' => $order['order_number'],
                'total_amount' => (float) $order['total_amount'],
                'amount' => (float) $order['amount'],
                'payment_type' => $order['payment_type'],
                'deposit_percentage' => (float) ($order['deposit_percentage'] ?? 0),
                'status' => $order['order_status'],
                'payment_status' => $order['payment_status'],
                'created_at' => $order['order_created_at'],
                'shipping_address' => $order['shipping_address'],
                'billing_address' => $order['billing_address'],
            ],
            'customer' => ['first_name' => $order['first_name'], 'last_name' => $order['last_name'], 'email' => $order['email'], 'phone' => $order['phone']],
            'items' => [
                'configurations' => array_map(static fn(array $item): array => [
                    'id' => $item['id'], 'name' => $item['config_name'] ?? 'Configuration personnalisée', 'prompt' => $item['prompt'],
                    'quantity' => (int) $item['quantity'], 'unit_price' => (float) $item['unit_price'], 'total_price' => (float) $item['total_price'],
                    'thumbnail_url' => $item['thumbnail_url'], 'config_data' => json_decode((string) $item['config_data'], true),
                ], $orderData['configurations']),
                'samples' => array_map(static fn(array $sample): array => [
                    'id' => $sample['id'], 'name' => $sample['sample_name'], 'type' => $sample['sample_type_name'], 'material' => $sample['material'],
                    'hex' => $sample['hex'], 'image_url' => $sample['image_url'], 'quantity' => (int) $sample['quantity'], 'price' => (float) $sample['price'],
                ], $orderData['samples']),
            ],
            'payment_link' => ['token' => $order['token'], 'expires_at' => $order['expires_at'], 'status' => $order['status']],
        ]]);
    }
}
