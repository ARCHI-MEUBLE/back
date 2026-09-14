<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminConfigurationRoutes
{
    private const VALID_STATUSES = ['en_attente_validation', 'validee', 'payee', 'en_production', 'livree', 'annulee', 'en_commande'];

    public function __construct(private readonly ConfigurationRepository $configurations) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/configurations', [new AdminGuard()])->get(null, fn(Request $r): Response => $this->get($r));
    }

    private function get(Request $r): Response
    {
        if ($r->queryString('id') !== null) {
            $id = filter_var($r->queryString('id'), FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                throw new DomainException('ID invalide');
            }
            $config = $this->configurations->adminFindById($id);
            if ($config === null) {
                throw new NotFoundException('Configuration introuvable');
            }
            return Response::json(['configuration' => $config]);
        }
        $limit = self::boundedInt($r->queryString('limit'), 100, 1, 500);
        $offset = self::boundedInt($r->queryString('offset'), 0, 0, PHP_INT_MAX);
        $status = $r->queryString('status');
        if ($status !== null && !in_array($status, self::VALID_STATUSES, true)) {
            throw new DomainException('Statut invalide');
        }
        return Response::json([
            'configurations' => $this->configurations->adminList($status, $limit, $offset),
            'total' => $this->configurations->adminCount($status),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    private static function boundedInt(?string $value, int $default, int $min, int $max): int
    {
        if ($value === null) {
            return $default;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        return ($filtered === false || $filtered < $min || $filtered > $max) ? $default : $filtered;
    }
}
