<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use RuntimeException;

final class ExportPaymentsRoutes
{
    private const STATUS_LABELS = ['pending' => 'En attente', 'paid' => 'Payé', 'failed' => 'Échoué', 'refunded' => 'Remboursé'];

    public function __construct(private readonly Connection $db) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/export-payments', [new AdminGuard()])->get(null, fn(Request $r): Response => $this->export($r));
    }

    private function export(Request $r): Response
    {
        $period = $r->queryString('period') ?? '30d';
        $rows = $this->db->query(
            'SELECT o.id, o.order_number, o.total_amount, o.payment_method, o.payment_status, o.stripe_payment_intent_id, o.created_at, o.updated_at,
                    c.first_name, c.last_name, c.email, c.phone
             FROM orders o JOIN customers c ON o.customer_id = c.id
             WHERE o.created_at >= ? ORDER BY o.created_at DESC',
            [PaymentPeriod::since($period)],
        );
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Unable to open temporary stream');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['ID', 'N° Commande', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Montant', 'Méthode de paiement', 'Statut', 'Stripe Payment Intent', 'Date création', 'Date mise à jour'], ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['id'], $row['order_number'], $row['first_name'], $row['last_name'], $row['email'], $row['phone'] ?? '',
                number_format((float) $row['total_amount'], 2, ',', ' ') . ' €',
                PaymentPeriod::methodLabel($row['payment_method']),
                self::STATUS_LABELS[$row['payment_status']] ?? ucfirst($row['payment_status']),
                $row['stripe_payment_intent_id'] ?? '',
                date('d/m/Y H:i', strtotime($row['created_at'])),
                date('d/m/Y H:i', strtotime($row['updated_at'] ?? $row['created_at'])),
            ], ';', '"', '\\');
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);
        return Response::text($csv, 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="paiements_' . $period . '_' . date('Y-m-d') . '.csv"')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
}
