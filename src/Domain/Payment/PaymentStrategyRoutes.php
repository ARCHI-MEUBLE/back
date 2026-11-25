<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Shared\DomainException;
use App\Http\Guard\AdminEmailGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class PaymentStrategyRoutes
{
    public function __construct(private readonly PaymentStrategyRepository $strategies) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/orders/payment-strategy', [new AdminEmailGuard()])->post(null, function (Request $r): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['order_id']) || $data['order_id'] === '' || !isset($data['strategy']) || $data['strategy'] === '') {
                throw new DomainException('order_id et strategy requis');
            }
            $percentage = $data['deposit_percentage'] ?? null;
            if ($percentage !== null && $percentage !== '' && !is_numeric($percentage)) {
                throw new DomainException("Le pourcentage d'acompte doit être un nombre entre 1 et 99");
            }
            $amounts = $this->strategies->update(
                (int) $data['order_id'],
                (string) $data['strategy'],
                $percentage === null || $percentage === '' ? null : (float) $percentage,
            );
            return Response::json(['success' => true, 'message' => 'Stratégie de paiement mise à jour. Les liens de paiement déjà envoyés ont été désactivés.'] + $amounts);
        });
    }
}
