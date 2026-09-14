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

    public function send(string $to, string $subject, string $html): bool
    {
        return (bool) $this->client()->send($to, $subject, $html);
    }

    public function sendOrderCancelled(string $email, string $name, string $orderNumber): void
    {
        $this->client()->sendOrderCancelledEmail($email, $name, $orderNumber);
    }

    public function sendOrderStatusUpdate(string $email, string $name, string $orderNumber, string $status, int $orderId): void
    {
        $this->client()->sendOrderStatusUpdateEmail($email, $name, $orderNumber, $status, $orderId);
    }

    public function sendOrderConfirmation(array $order, array $customer, array $items, string $paymentType): void
    {
        $this->client()->sendOrderConfirmation($order, $customer, $items, $paymentType);
    }

    public function sendNewOrderNotificationToAdmin(array $order, array $customer, array $items): void
    {
        $this->client()->sendNewOrderNotificationToAdmin($order, $customer, $items);
    }

    public function sendPaymentFailed(array $order, array $customer): void
    {
        $this->client()->sendPaymentFailedEmail($order, $customer);
    }

    private function client(): \EmailService
    {
        require_once $this->rootDir . '/backend/services/EmailService.php';
        return new \EmailService();
    }
}
