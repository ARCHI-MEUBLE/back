<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Stripe\StripeGateway;
use App\Lib\Logger;
use Stripe\Exception\SignatureVerificationException;
use Throwable;
use UnexpectedValueException;

final class StripeWebhookRoutes
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly Logger $logger,
        private readonly PaymentSucceededHandler $succeeded,
        private readonly PaymentFailedHandler $failed,
        private readonly PaymentRefundHandler $refunds,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('stripe/webhook')->post(null, fn(Request $r): Response => $this->handle($r));
    }

    private function handle(Request $r): Response
    {
        try {
            $this->stripe->ensureConfigured();
            if (!$this->stripe->hasUsableWebhookSecret()) {
                $this->logger->error('Stripe webhook rejected: STRIPE_WEBHOOK_SECRET is not configured');
                return Response::empty(503);
            }
            try {
                $event = $this->stripe->constructWebhookEvent($r->rawBody(), $r->header('stripe-signature') ?? '');
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

    private function dispatch(object $event): void
    {
        $type = isset($event->type) && is_string($event->type) ? $event->type : null;
        $object = isset($event->data->object) && is_object($event->data->object) ? $event->data->object : null;
        if ($object === null) {
            error_log("Unhandled webhook event type: {$type}");
            return;
        }
        match ($type) {
            'payment_intent.succeeded' => $this->succeeded->handle($object),
            'payment_intent.payment_failed' => $this->failed->handle($object),
            'charge.refunded' => $this->refunds->handle($object),
            default => error_log("Unhandled webhook event type: {$type}"),
        };
    }
}
