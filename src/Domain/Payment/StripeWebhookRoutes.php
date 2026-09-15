<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Stripe\StripeGateway;
use App\Infrastructure\Stripe\StripeObjectReader;
use App\Lib\Logger;
use Stripe\Exception\SignatureVerificationException;
use Throwable;
use UnexpectedValueException;

final class StripeWebhookRoutes
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly Connection $db,
        private readonly Logger $logger,
        private readonly PaymentSucceededHandler $succeeded,
        private readonly PaymentFailedHandler $failed,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('stripe/webhook')->post(null, fn(Request $r): Response => $this->handle($r));
    }

    private function handle(Request $r): Response
    {
        try {
            $this->stripe->ensureConfigured();
            $payload = $r->rawBody();
            $signature = $r->header('stripe-signature') ?? '';
            try {
                $event = $this->stripe->hasUsableWebhookSecret()
                    ? $this->stripe->constructWebhookEvent($payload, $signature)
                    : json_decode($payload, false);
            } catch (UnexpectedValueException|SignatureVerificationException) {
                return Response::empty(400);
            }
            $this->dispatch($event);
            return Response::json(['received' => true]);
        } catch (Throwable $e) {
            $this->logger->error('Webhook error', ['message' => $e->getMessage()]);
            return Response::empty(500);
        }
    }

    private function dispatch(mixed $event): void
    {
        $type = is_object($event) && isset($event->type) ? $event->type : null;
        $object = is_object($event) && isset($event->data->object) && is_object($event->data->object) ? $event->data->object : null;
        if ($object === null) {
            error_log("Unhandled webhook event type: {$type}");
            return;
        }
        match ($type) {
            'payment_intent.succeeded' => $this->succeeded->handle($object),
            'payment_intent.payment_failed' => $this->failed->handle($object),
            'charge.refunded' => $this->handleRefund($object),
            default => error_log("Unhandled webhook event type: {$type}"),
        };
    }

    private function handleRefund(object $charge): void
    {
        $paymentIntentId = StripeObjectReader::string($charge, 'payment_intent');
        $order = $this->db->queryOne('SELECT id FROM orders WHERE stripe_payment_intent_id = ?', [$paymentIntentId]);
        if ($order === null) {
            return;
        }
        $this->db->execute("UPDATE orders SET payment_status = 'refunded', status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$order['id']]);
    }
}
