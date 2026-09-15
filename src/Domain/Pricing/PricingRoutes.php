<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class PricingRoutes
{
    private const DEFAULT_PRICING = [
        'id' => 0, 'name' => 'default', 'description' => 'Tarif par défaut',
        'price_per_m3' => 2500.0, 'is_active' => 1,
    ];

    public function __construct(private readonly PricingRepository $pricing) {}

    public function register(RouteCollection $routes): void
    {
        $admin = [new AdminGuard()];
        $script = $routes->script('pricing/index');

        $script->get(null, function (Request $r): Response {
            $name = $r->queryString('name');
            if ($name === null) {
                return Response::json(['success' => true, 'data' => $this->pricing->active()]);
            }
            $pricing = $this->pricing->findActiveByName($name) ?? $this->pricing->firstActive() ?? self::DEFAULT_PRICING;
            return Response::json(['success' => true, 'data' => $pricing]);
        });

        $script->post(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['name']) || $data['name'] === '' || !isset($data['price_per_m3'])) {
                return self::error('Nom et prix par m³ requis');
            }
            $id = $this->pricing->create((string) $data['name'], (string) ($data['description'] ?? ''), (float) $data['price_per_m3'], (bool) ($data['is_active'] ?? true));
            return Response::json(['success' => true, 'message' => 'Tarif créé avec succès', 'data' => ['id' => (string) $id]]);
        }, $admin);

        $script->put(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['id']) || $data['id'] === '' || $data['id'] === 0) {
                return self::error('ID requis');
            }
            $this->pricing->update((int) $data['id'], $data);
            return Response::json(['success' => true, 'message' => 'Tarif mis à jour avec succès']);
        }, $admin);

        $script->delete(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['id']) || $data['id'] === '' || $data['id'] === 0) {
                return self::error('ID requis');
            }
            $this->pricing->deactivate((int) $data['id']);
            return Response::json(['success' => true, 'message' => 'Tarif supprimé avec succès']);
        }, $admin);
    }

    private static function error(string $message): Response
    {
        return Response::json(['success' => false, 'error' => $message], 400);
    }
}
