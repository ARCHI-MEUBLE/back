<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminConfigurationStatusRoutes
{
    private const VALID_STATUSES = ['en_attente_validation', 'validee', 'payee', 'en_production', 'livree', 'annulee', 'en_commande'];

    public function __construct(private readonly ConfigurationRepository $configurations) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/update-configuration-status', [new AdminGuard()])->post(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['id'], $data['status'])) {
                throw new DomainException('ID et status requis');
            }
            $status = (string) $data['status'];
            if (!in_array($status, self::VALID_STATUSES, true)) {
                throw new DomainException('Statut invalide');
            }
            $id = (int) $data['id'];
            if ($this->configurations->findWithOrderId($id) === null) {
                throw new NotFoundException('Configuration introuvable');
            }
            $this->configurations->update($id, ['status' => $status]);
            return Response::json(['success' => true, 'message' => 'Statut mis à jour', 'id' => $id, 'status' => $status]);
        });
    }
}
