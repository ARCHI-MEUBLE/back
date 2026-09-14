<?php

declare(strict_types=1);

namespace App\Infrastructure\Installment;

final class LegacyInstallmentGateway
{
    public function __construct(private readonly string $rootDir) {}

    public function createInstallments(int $orderId, int $customerId, float $totalAmount): void
    {
        $this->client()->createInstallments($orderId, $customerId, $totalAmount);
    }

    private function client(): \InstallmentService
    {
        require_once $this->rootDir . '/backend/services/InstallmentService.php';
        return new \InstallmentService();
    }
}
