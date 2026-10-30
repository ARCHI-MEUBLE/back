<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Config\MailSettings;
use App\Domain\Email\AdminOrderNotificationEmail;
use App\Domain\Email\ConfigurationNotificationEmail;
use App\Domain\Email\ConfigurationRequestEmail;
use App\Domain\Email\ContactRequestNotificationEmail;
use App\Domain\Email\ItemDisplayNameResolver;
use App\Domain\Email\LoginCodeEmail;
use App\Domain\Email\OrderCancelledEmail;
use App\Domain\Email\OrderConfirmationEmail;
use App\Domain\Email\OrderStatusUpdateEmail;
use App\Domain\Email\PasswordResetEmail;
use App\Domain\Email\PaymentFailedEmail;
use App\Domain\Email\PaymentLinkEmail;
use App\Domain\Email\QuoteRequestNotificationEmail;
use App\Domain\Email\VerificationEmail;
use App\Domain\Order\OrderRepository;
use App\Lib\Html;

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
        $this->mailer->send($email, PasswordResetEmail::subject(), PasswordResetEmail::html(Html::e($name), Html::e($resetUrl)));
    }

    public function sendVerificationCode(string $email, string $name, string $code): bool
    {
        return $this->mailer->send($email, VerificationEmail::subject(), VerificationEmail::html(Html::e($name), Html::e($code)));
    }

    public function sendLoginCode(string $email, string $name, string $code): bool
    {
        return $this->mailer->send($email, LoginCodeEmail::subject(), LoginCodeEmail::html(Html::e($name), Html::e($code)));
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
            ConfigurationNotificationEmail::html($configuration, Html::escapeAll($customer), Html::e($this->frontendUrl), Html::e($this->mail->backendUrl)),
        );
    }

    public function sendConfigurationRequest(array $request, array $share): bool
    {
        $link = "{$this->frontendUrl}/configurator/" . rawurlencode($share['configurator_id']) . '?partage=' . rawurlencode($share['code']);
        return $this->mailer->send(
            $this->mail->adminEmail,
            ConfigurationRequestEmail::subject((string) $request['name']),
            ConfigurationRequestEmail::html($request, $share, $link),
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
            QuoteRequestNotificationEmail::html(Html::e($firstName), Html::e($lastName), Html::e($email), Html::e($phone), Html::e($description), $fileCount, Html::e($this->dashboardUrl())),
        );
    }

    public function sendNewContactRequestNotification(string $name, string $email, string $phone, string $company, string $subject, string $message): void
    {
        $this->mailer->send(
            $this->mail->adminEmail,
            ContactRequestNotificationEmail::subject($name, $subject),
            ContactRequestNotificationEmail::html(Html::e($name), Html::e($email), Html::e($phone), Html::e($company), Html::e($subject), Html::e($message), Html::e($this->dashboardUrl())),
        );
    }

    private function dashboardUrl(): string
    {
        return $this->mail->adminPath === '' ? $this->frontendUrl : "{$this->frontendUrl}/{$this->mail->adminPath}/dashboard";
    }

    public function sendOrderCancelled(string $email, string $name, string $orderNumber): void
    {
        $this->mailer->send($email, OrderCancelledEmail::subject($orderNumber), OrderCancelledEmail::html(Html::e($name), Html::e($orderNumber)));
    }

    public function sendOrderStatusUpdate(string $email, string $name, string $orderNumber, string $status, int $orderId): void
    {
        $this->mailer->send($email, OrderStatusUpdateEmail::subject($orderNumber), OrderStatusUpdateEmail::html(Html::e($name), Html::e($orderNumber), $status, Html::e(rtrim($this->frontendUrl, '/') . '/order-confirmation/' . $orderId)));
    }

    public function sendOrderConfirmation(array $order, array $customer, array $items, string $paymentType): void
    {
        $itemRows = array_map(fn(array $item): array => [
            'name' => Html::e($this->itemNames->resolve($item)),
            'quantity' => (int) $item['quantity'],
            'price' => (float) ($item['price'] ?? $item['unit_price'] ?? 0),
        ], $items);
        $samples = Html::escapeAll($this->orders->samplesFor((int) $order['id']));
        $this->mailer->send(
            $customer['email'],
            OrderConfirmationEmail::subject((string) $order['order_number'], $paymentType),
            OrderConfirmationEmail::html(Html::escapeAll($order), Html::escapeAll($customer), $itemRows, $samples, $paymentType, Html::e($this->frontendUrl)),
        );
    }

    public function sendNewOrderNotificationToAdmin(array $order, array $customer, array $items, string $paymentType = 'full'): void
    {
        $itemNames = array_map(fn(array $item): array => ['name' => Html::e($this->itemNames->resolve($item)), 'quantity' => (int) $item['quantity']], $items);
        $this->mailer->send(
            $this->mail->adminEmail,
            AdminOrderNotificationEmail::subject((string) $order['order_number'], (string) $customer['first_name'], (string) $customer['last_name'], $paymentType),
            AdminOrderNotificationEmail::html(Html::escapeAll($order), Html::escapeAll($customer), $itemNames, $paymentType),
        );
    }

    public function sendPaymentFailed(array $order, array $customer): void
    {
        $this->mailer->send(
            $customer['email'],
            PaymentFailedEmail::subject((string) $order['order_number']),
            PaymentFailedEmail::html(Html::e($customer['first_name'] ?? ''), Html::e($order['order_number'] ?? ''), Html::e($this->mail->adminEmail)),
        );
    }

    public function sendPaymentLink(string $email, string $name, string $orderNumber, string $url, string $expiresAt, float $amount): void
    {
        $this->mailer->send($email, PaymentLinkEmail::subject($orderNumber), PaymentLinkEmail::html(Html::e($name), Html::e($orderNumber), Html::e($url), Html::e($expiresAt), $amount));
    }
}
