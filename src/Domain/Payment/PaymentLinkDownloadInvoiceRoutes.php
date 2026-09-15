<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Customer\CustomerRepository;
use App\Domain\Order\OrderRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Invoice\LegacyInvoiceGateway;

final class PaymentLinkDownloadInvoiceRoutes
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly LegacyInvoiceGateway $invoices,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('payment-link/download-invoice')->get(null, fn(Request $r): Response => $this->download($r));
    }

    private function download(Request $r): Response
    {
        $intentId = $r->queryString('payment_intent_id');
        if ($intentId === null) {
            throw new DomainException('payment_intent_id requis');
        }
        $order = $this->orders->findByStripeIntentId($intentId);
        if ($order === null) {
            throw new NotFoundException('Commande introuvable');
        }
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
        return Response::file($pdfPath, 'application/pdf')->withHeader('Content-Disposition', 'inline; filename="' . $invoice['filename'] . '"');
    }
}
