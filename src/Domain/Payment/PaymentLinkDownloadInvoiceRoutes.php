<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Customer\CustomerRepository;
use App\Domain\Order\OrderRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\Invoice\LegacyInvoiceGateway;
use App\Infrastructure\Stripe\StripeGateway;

final class PaymentLinkDownloadInvoiceRoutes
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly LegacyInvoiceGateway $invoices,
        private readonly StripeGateway $stripe,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('payment-link/download-invoice')->get(null, fn(Request $r, Session $s): Response => $this->download($r, $s));
    }

    private function download(Request $r, Session $s): Response
    {
        $intentId = $r->queryString('payment_intent_id');
        if ($intentId === null) {
            throw new DomainException('payment_intent_id requis');
        }
        $order = $this->orders->findByStripeIntentId($intentId);
        if ($order === null) {
            throw new NotFoundException('Commande introuvable');
        }
        $this->assertCanDownload($r, $s, $order, $intentId);
        if ($order['payment_status'] !== 'paid') {
            throw new DomainException('La commande doit être payée pour télécharger la facture');
        }
        $fullOrder = (array) $this->orders->findById((int) $order['id']);
        $customer = (array) $this->customers->findRawById((int) $order['customer_id']);
        $items = $this->orders->itemsFor((int) $order['id']);
        $samples = $this->orders->samplesFor((int) $order['id']);
        $invoice = $this->invoices->generateInvoice($fullOrder, $customer, $items, $samples);
        $pdfPath = $invoice['filepath'];
        if (!is_file($pdfPath) || filesize($pdfPath) <= 500) {
            throw new NotFoundException('Facture PDF introuvable');
        }
        return Response::file($pdfPath, 'application/pdf')->withHeader('Content-Disposition', Response::disposition('inline', (string) $invoice['filename']));
    }

    private function assertCanDownload(Request $r, Session $s, array $order, string $intentId): void
    {
        if ($s->isAdmin() || ($s->customerId() !== null && (int) $order['customer_id'] === $s->customerId())) {
            return;
        }
        $secret = $r->queryString('payment_intent_client_secret') ?? '';
        if (!str_starts_with($secret, $intentId . '_secret_')) {
            throw new ForbiddenException('Accès refusé à cette facture');
        }
        $this->stripe->ensureConfigured();
        if (!hash_equals((string) $this->stripe->retrievePaymentIntent($intentId)->client_secret, $secret)) {
            throw new ForbiddenException('Accès refusé à cette facture');
        }
    }
}
