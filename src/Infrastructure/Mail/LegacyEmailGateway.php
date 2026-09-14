<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

final class LegacyEmailGateway
{
    public function __construct(private readonly string $rootDir) {}

    public function sendPasswordReset(string $email, string $name, string $resetUrl): void
    {
        $this->client()->sendPasswordResetEmail($email, $name, $resetUrl);
    }

    public function sendVerificationCode(string $email, string $name, string $code): bool
    {
        return (bool) $this->client()->sendVerificationEmail($email, $name, $code);
    }

    public function sendNewConfigurationNotification(array $configuration, array $customer): void
    {
        $this->client()->sendNewConfigurationNotificationToAdmin($configuration, $customer);
    }

    private function client(): \EmailService
    {
        require_once $this->rootDir . '/backend/services/EmailService.php';
        return new \EmailService();
    }
}
