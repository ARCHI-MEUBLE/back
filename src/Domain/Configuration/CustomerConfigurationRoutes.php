<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class CustomerConfigurationRoutes
{
    public function __construct(private readonly ConfigurationRepository $configurations) {}

    public function register(RouteCollection $routes): void
    {
        $guard = [new CustomerGuard()];
        $script = $routes->script('configurations/list', $guard);

        $script->get(null, function (Request $r, Session $s): Response {
            $customerId = (int) $s->customerId();
            if ($r->queryString('id') !== null) {
                $configuration = $this->findOwned((int) $r->queryString('id'), $customerId);
                return Response::json(['configuration' => $configuration]);
            }
            return Response::json([
                'configurations' => $this->configurations->findByCustomerId($customerId),
                'total' => $this->configurations->countByCustomerId($customerId),
            ]);
        });

        $script->delete(null, function (Request $r, Session $s): Response {
            $id = $r->queryString('id');
            if ($id === null) {
                throw new DomainException('ID requis');
            }
            $existing = $this->configurations->findWithOrderId((int) $id);
            if ($existing === null || (string) $existing['user_id'] !== (string) $s->customerId()) {
                throw new ForbiddenException('Accès refusé');
            }
            $this->configurations->delete((int) $id);
            return Response::json(['success' => true, 'message' => 'Configuration supprimée']);
        });
    }

    private function findOwned(int $id, int $customerId): array
    {
        $configuration = $this->configurations->findWithOrderId($id);
        if ($configuration === null) {
            throw new NotFoundException('Configuration introuvable');
        }
        if ((string) $configuration['user_id'] !== (string) $customerId) {
            throw new ForbiddenException('Accès refusé');
        }
        return $configuration;
    }
}
