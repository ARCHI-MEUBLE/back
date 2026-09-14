<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoice;

final class LegacyInvoiceGateway
{
    public function __construct(private readonly string $rootDir) {}

    public function generateInvoice(array $order, array $customer, array $items, array $samples = []): array
    {
        return $this->client()->generateInvoice($order, $customer, $items, $samples);
    }

    private function client(): \InvoiceService
    {
        require_once $this->rootDir . '/backend/services/InvoiceService.php';
        return new \InvoiceService();
    }
}
