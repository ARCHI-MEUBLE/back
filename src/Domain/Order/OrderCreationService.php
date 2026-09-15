<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Db\Connection;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Shared\DomainException;

final class OrderCreationService
{
    public function __construct(
        private readonly Connection $db,
        private readonly OrderCreationRepository $repository,
        private readonly CustomerRepository $customers,
    ) {}

    public function createFromCart(int $customerId, string $shippingAddress, string $billingAddress, string $paymentMethod, ?string $notes): array
    {
        return $this->db->transaction(function () use ($customerId, $shippingAddress, $billingAddress, $paymentMethod, $notes): array {
            $cartItems = $this->repository->cartConfigItems($customerId);
            $sampleItems = $this->repository->cartSampleItems($customerId);
            $catalogueItems = $this->repository->cartCatalogueItems($customerId);
            $facadeItems = $this->repository->cartFacadeItems($customerId);
            if ($cartItems === [] && $sampleItems === [] && $catalogueItems === [] && $facadeItems === []) {
                throw new DomainException('Panier vide', 500);
            }
            $total = self::total($cartItems, $sampleItems, $catalogueItems, $facadeItems);
            $orderNumber = self::generateOrderNumber();
            $status = $cartItems === [] ? 'confirmed' : 'pending';
            $orderId = $this->repository->insertOrder($customerId, $orderNumber, $total, $shippingAddress, $billingAddress, $paymentMethod, $notes, $status);
            foreach ($cartItems as $item) {
                $this->repository->insertConfigItem($orderId, $item);
            }
            foreach ($sampleItems as $sample) {
                $this->repository->insertSampleItem($orderId, $sample);
            }
            foreach ($catalogueItems as $item) {
                $this->repository->insertCatalogueItem($orderId, $item);
            }
            foreach ($facadeItems as $facade) {
                $this->repository->insertFacadeItem($orderId, $facade);
            }
            $this->repository->clearCart($customerId);
            return [
                'id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $total,
                'status' => $status,
                'samples_count' => count($sampleItems),
                'facades_count' => count($facadeItems),
                'needs_validation' => $cartItems !== [],
                'customer' => $this->customers->findFullById($customerId),
            ];
        });
    }

    private static function total(array $cartItems, array $sampleItems, array $catalogueItems, array $facadeItems): float
    {
        $total = 0.0;
        foreach ($cartItems as $item) {
            $total += ((float) $item['configuration']['price']) * $item['quantity'];
        }
        foreach ($sampleItems as $sample) {
            $total += ((float) ($sample['unit_price'] ?? 0)) * $sample['quantity'];
        }
        foreach ($catalogueItems as $item) {
            $total += ((float) $item['unit_price']) * $item['quantity'];
        }
        foreach ($facadeItems as $facade) {
            $total += ((float) $facade['unit_price']) * $facade['quantity'];
        }
        return $total;
    }

    private static function generateOrderNumber(): string
    {
        return 'ORD-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}
