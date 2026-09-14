<?php

declare(strict_types=1);

namespace App\Infrastructure\PaymentLink;

final class LegacyPaymentLinkGateway
{
    public function __construct(private readonly string $rootDir) {}

    public function markAsUsed(string $token): void
    {
        $this->client()->markAsUsed($token);
    }

    private function client(): \PaymentLink
    {
        require_once $this->rootDir . '/backend/models/PaymentLink.php';
        return new \PaymentLink();
    }
}
