<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Config\MailSettings;
use App\Domain\Email\AdminOrderNotificationEmail;
use App\Domain\Email\ConfigurationNotificationEmail;
use App\Domain\Email\ContactRequestNotificationEmail;
use App\Domain\Email\ItemDisplayNameResolver;
use App\Domain\Email\OrderCancelledEmail;
use App\Domain\Email\OrderConfirmationEmail;
use App\Domain\Email\OrderStatusUpdateEmail;
use App\Domain\Email\PasswordResetEmail;
use App\Domain\Email\PaymentFailedEmail;
use App\Domain\Email\PaymentLinkEmail;
use App\Domain\Email\QuoteRequestNotificationEmail;
use App\Domain\Email\VerificationEmail;
use App\Domain\Order\OrderRepository;

final class EmailGateway
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly ItemDisplayNameResolver $itemNames,
        private readonly OrderRepository $orders,
        private readonly MailSettings $mail,
        private readonly string $frontendUrl,
    ) {}

    public function sendPasswordReset(string $email, string $name, string $resetUrl): void
    {
        $this->mailer->send($email, PasswordResetEmail::subject(), PasswordResetEmail::html($name, $resetUrl));
    }

    public function sendVerificationCode(string $email, string $name, string $code): bool
    {
        return $this->mailer->send($email, VerificationEmail::subject(), VerificationEmail::html($name, $code));
    }

    public function sendNewConfigurationNotification(array $configuration, array $customer): void
    {
        if (!isset($configuration['id'])) {
            error_log('Cannot send notification: invalid config data');
            return;
        }
        $this->mailer->send(
            $this->mail->adminEmail,
            ConfigurationNotificationEmail::subject($configuration, $customer),
            ConfigurationNotificationEmail::html($configuration, $customer, $this->frontendUrl, $this->mail->backendUrl),
        );
    }

    public function send(string $to, string $subject, string $html): bool
    {
        return $this->mailer->send($to, $subject, $html);
    }

    public function sendNewQuoteRequestNotification(string $firstName, string $lastName, string $email, string $phone, string $description, int $fileCount): void
    {
        $this->mailer->send(
            $this->mail->adminEmail,
            QuoteRequestNotificationEmail::subject($firstName, $lastName),
            QuoteRequestNotificationEmail::html($firstName, $lastName, $email, $phone, $description, $fileCount, $this->dashboardUrl()),
        );
    }

    public function sendNewContactRequestNotification(string $name, string $email, string $phone, string $company, string $subject, string $message): void
    {
        $this->mailer->send(
            $this->mail->adminEmail,
            ContactRequestNotificationEmail::subject($name, $subject),
            ContactRequestNotificationEmail::html($name, $email, $phone, $company, $subject, $message, $this->dashboardUrl()),
        );
    }

    private function dashboardUrl(): string
    {
        return $this->mail->adminPath === '' ? $this->frontendUrl : "{$this->frontendUrl}/{$this->mail->adminPath}/dashboard";
    }

    public function sendOrderCancelled(string $email, string $name, string $orderNumber): void
    {
        $this->mailer->send($email, OrderCancelledEmail::subject($orderNumber), OrderCancelledEmail::html($name, $orderNumber));
    }

    public function sendOrderStatusUpdate(string $email, string $name, string $orderNumber, string $status, int $orderId): void
    {
        $this->mailer->send($email, OrderStatusUpdateEmail::subject($orderNumber), OrderStatusUpdateEmail::html($name, $orderNumber, $status, $this->frontendUrl));
    }

    public function sendOrderConfirmation(array $order, array $customer, array $items, string $paymentType): void
    {
        $itemRows = array_map(fn(array $item): array => [
            'name' => $this->itemNames->resolve($item),
            'quantity' => $item['quantity'],
            'price' => (float) ($item['price'] ?? $item['unit_price'] ?? 0),
        ], $items);
        $samples = $this->orders->samplesFor((int) $order['id']);
        $this->mailer->send(
            $customer['email'],
            OrderConfirmationEmail::subject((string) $order['order_number'], $paymentType),
            OrderConfirmationEmail::html($order, $customer, $itemRows, $samples, $paymentType, $this->frontendUrl),
        );
    }

    public function sendNewOrderNotificationToAdmin(array $order, array $customer, array $items, string $paymentType = 'full'): void
    {
        $itemNames = array_map(fn(array $item): array => ['name' => $this->itemNames->resolve($item), 'quantity' => $item['quantity']], $items);
        $this->mailer->send(
            $this->mail->adminEmail,
            AdminOrderNotificationEmail::subject((string) $order['order_number'], (string) $customer['first_name'], (string) $customer['last_name'], $paymentType),
            AdminOrderNotificationEmail::html($order, $customer, $itemNames, $paymentType),
        );
    }

    public function sendPaymentFailed(array $order, array $customer): void
    {
        $this->mailer->send(
            $customer['email'],
            PaymentFailedEmail::subject((string) $order['order_number']),
            PaymentFailedEmail::html((string) $customer['first_name'], (string) $order['order_number'], $this->mail->adminEmail),
        );
    }

    public function sendPaymentLink(string $email, string $name, string $orderNumber, string $url, string $expiresAt, float $amount): void
    {
        $this->mailer->send($email, PaymentLinkEmail::subject($orderNumber), PaymentLinkEmail::html($name, $orderNumber, $url, $expiresAt, $amount));
    }
}
