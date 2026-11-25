<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Db\Connection;
use App\Domain\Cart\CartCatalogueRepository;
use App\Domain\Cart\CartShipping;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Facade\FacadePriceCalculator;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Lib\Json;
use App\Lib\Validation\ValidationException;

final class OrderCreationService
{
    public function __construct(
        private readonly Connection $db,
        private readonly OrderCreationRepository $repository,
        private readonly CustomerRepository $customers,
        private readonly FacadePriceCalculator $facadePrices,
        private readonly CartShipping $shipping,
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
            if ($this->repository->foreignConfigurationCount($customerId) > 0) {
                throw new ForbiddenException('Une configuration de votre panier ne vous appartient pas : retirez-la avant de commander.');
            }
            if ($this->repository->unvalidatedConfigurationCount($customerId) > 0) {
                throw new DomainException('Une configuration de votre panier a été modifiée et doit être revalidée par notre équipe avant la commande.', 409);
            }
            self::assertCatalogueOrderable($catalogueItems);
            $facadeItems = array_map($this->repriced(...), $facadeItems);
            $parcelSubtotal = self::linesTotal($sampleItems) + self::linesTotal($catalogueItems) + self::linesTotal($facadeItems);
            $shipping = $this->shipping->quote($cartItems, $parcelSubtotal, $sampleItems !== [] || $catalogueItems !== [] || $facadeItems !== []);
            $shippingAmount = (float) $shipping['total'];
            $total = round(self::configurationsTotal($cartItems) + $parcelSubtotal + $shippingAmount, 2);
            $orderNumber = self::generateOrderNumber();
            $status = 'pending';
            $orderId = $this->repository->insertOrder($customerId, $orderNumber, $total, $shippingAmount, $shippingAddress, $billingAddress, $paymentMethod, $notes, $status);
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
                'shipping_amount' => $shippingAmount,
                'status' => $status,
                'samples_count' => count($sampleItems),
                'facades_count' => count($facadeItems),
                'needs_validation' => $cartItems !== [],
                'customer' => $this->customers->findFullById($customerId),
            ];
        });
    }

    private function repriced(array $facade): array
    {
        $config = json_decode((string) $facade['config_data'], true);
        try {
            $normalized = $this->facadePrices->normalize(is_array($config) ? $config : []);
            $facade['unit_price'] = $this->facadePrices->unitPrice($normalized);
        } catch (ValidationException $e) {
            throw new DomainException('Une façade de votre panier ne peut plus être commandée (' . $e->getMessage() . ') : retirez-la puis configurez-la de nouveau.');
        }
        $facade['config_data'] = Json::encode($normalized);
        return $facade;
    }

    private static function assertCatalogueOrderable(array $catalogueItems): void
    {
        foreach ($catalogueItems as $item) {
            if (($item['is_available'] ?? true) === false) {
                throw new DomainException(sprintf("L'article « %s » n'est plus disponible : retirez-le de votre panier.", (string) ($item['name'] ?? 'Article')));
            }
            CartCatalogueRepository::assertMinimum((int) $item['quantity'], (int) ($item['min_order_quantity'] ?? 1));
        }
    }

    private static function configurationsTotal(array $cartItems): float
    {
        $total = 0.0;
        foreach ($cartItems as $item) {
            $total += ((float) $item['configuration']['price']) * $item['quantity'];
        }
        return $total;
    }

    private static function linesTotal(array $rows): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += ((float) ($row['unit_price'] ?? 0)) * (int) $row['quantity'];
        }
        return $total;
    }

    private static function generateOrderNumber(): string
    {
        return 'ORD-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}
