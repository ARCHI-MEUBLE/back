<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Customer\CustomerRepository;
use App\Domain\Order\OrderRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\NotFoundException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\Invoice\LegacyInvoiceGateway;

final class OrderInvoiceRoutes
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly LegacyInvoiceGateway $invoices,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/invoice')->get(null, fn(Request $r, Session $s): Response => $this->show($r, $s));
    }

    private function show(Request $r, Session $s): Response
    {
        $isClient = $s->customerId() !== null;
        $isAdmin = $s->isAdmin();
        if (!$isClient && !$isAdmin) {
            throw new UnauthorizedException('Non authentifié');
        }
        $id = $r->queryString('id');
        if ($id === null) {
            throw new DomainException('ID de commande requis');
        }
        $orderId = (int) $id;
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new NotFoundException('Commande introuvable');
        }
        if ($isClient && (int) $order['customer_id'] !== (int) $s->customerId()) {
            throw new ForbiddenException('Accès refusé');
        }
        if ($order['payment_status'] !== 'paid') {
            throw new DomainException('La commande doit être payée pour générer une facture');
        }
        $customer = (array) $this->customers->findFullById((int) $order['customer_id']);
        $items = $this->orders->itemsFor($orderId);
        $samples = $this->orders->samplesFor($orderId);
        $invoice = $this->invoices->generateInvoice($order, $customer, $items, $samples);
        if ($r->queryString('json') === 'true') {
            return Response::json(['success' => true, 'invoice_number' => $invoice['invoice_number'], 'filename' => $invoice['filename'], 'download_url' => "/backend/api/orders/invoice.php?id={$orderId}&download=true"]);
        }
        if ($r->queryString('download') === 'true') {
            return $this->serveFile($invoice);
        }
        return Response::empty(200);
    }

    private function serveFile(array $invoice): Response
    {
        $pdfPath = $invoice['filepath'];
        $htmlPath = str_replace('.pdf', '.html', $pdfPath);
        if (is_file($pdfPath) && filesize($pdfPath) > 500) {
            return Response::file($pdfPath, 'application/pdf')->withHeader('Content-Disposition', 'inline; filename="' . $invoice['filename'] . '"');
        }
        if (is_file($htmlPath)) {
            return Response::file($htmlPath, 'text/html; charset=UTF-8')->withHeader('Content-Disposition', 'inline; filename="' . str_replace('.pdf', '.html', $invoice['filename']) . '"');
        }
        throw new NotFoundException('Fichiers de facture introuvables');
    }
}
