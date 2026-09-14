<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Notification\NotificationRepository;
use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\Mail\EmailGateway;
use Throwable;

final class AdminGeneratePaymentLinkRoutes
{
    public function __construct(
        private readonly PaymentLinkRepository $links,
        private readonly Connection $db,
        private readonly NotificationRepository $notifications,
        private readonly EmailGateway $mail,
        private readonly string $frontendUrl,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/generate-payment-link', [new AdminGuard()], ErrorStyle::Success)
            ->post(null, fn(Request $r, Session $s): Response => $this->generate($r, $s));
    }

    private function generate(Request $r, Session $s): Response
    {
        $data = $r->jsonOrEmpty();
        if (!isset($data['order_id'])) {
            throw new DomainException('ID de commande manquant');
        }
        $orderId = (int) $data['order_id'];
        $expiryDays = isset($data['expiry_days']) ? (int) $data['expiry_days'] : 30;
        $paymentType = $data['payment_type'] ?? 'full';
        $amount = isset($data['amount']) && (float) $data['amount'] > 0 ? (float) $data['amount'] : null;
        $adminEmail = (string) $s->string('admin_email');
        $link = $this->links->generateLink($orderId, $adminEmail, $expiryDays, $paymentType, $amount);
        $fullUrl = rtrim($this->frontendUrl, '/') . '/paiement/' . $link['token'];
        $this->notifyCustomer($orderId, $fullUrl, $link);
        return Response::json(['success' => true, 'data' => [
            'id' => $link['id'], 'token' => $link['token'], 'url' => $fullUrl, 'expires_at' => $link['expires_at'], 'order_id' => $orderId, 'created_by' => $adminEmail,
        ], 'message' => 'Lien de paiement généré avec succès'], 201);
    }

    private function notifyCustomer(int $orderId, string $url, array $link): void
    {
        $order = $this->db->queryOne('SELECT o.*, c.email as customer_email, c.first_name, c.last_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?', [$orderId]);
        if ($order === null) {
            return;
        }
        if (isset($order['customer_id'])) {
            $this->notifications->create((int) $order['customer_id'], 'payment_link_created', 'Lien de paiement généré', "Un lien de paiement a été généré pour la commande #{$orderId}", $orderId, 'order');
        }
        if (isset($order['customer_email']) && $order['customer_email'] !== '') {
            $name = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
            try {
                $this->mail->sendPaymentLink($order['customer_email'], $name !== '' ? $name : 'Client', $order['order_number'], $url, $link['expires_at'], (float) ($link['amount'] ?? $order['total_amount']));
            } catch (Throwable $e) {
                error_log('GENERATE LINK - Email Error: ' . $e->getMessage());
            }
        }
    }
}
