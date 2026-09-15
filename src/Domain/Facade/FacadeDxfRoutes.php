<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class FacadeDxfRoutes
{
    public function __construct(
        private readonly Connection $db,
        private readonly FacadeDxfGenerator $generator,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('facades/dxf')->get(null, function (Request $r, Session $s): Response {
            if (!$s->isAdmin()) {
                throw new UnauthorizedException();
            }
            $facadeId = $r->queryString('facade_id');
            if ($facadeId === null) {
                throw new DomainException('ID de façade requis');
            }
            $row = $this->db->queryOne('SELECT id, config_data FROM order_facade_items WHERE id = ?', [(int) $facadeId]);
            if ($row === null) {
                throw new NotFoundException('Façade non trouvée');
            }
            $config = is_string($row['config_data']) ? json_decode($row['config_data'], true) : $row['config_data'];
            if (!is_array($config) || $config === []) {
                throw new DomainException('Configuration de façade invalide');
            }
            $dxf = $this->generator->generate(
                ((float) ($config['width'] ?? 600)) / 1000,
                ((float) ($config['height'] ?? 800)) / 1000,
                ((float) ($config['depth'] ?? 19)) / 1000,
                $config['drillings'] ?? [],
                $facadeId,
                (string) ($config['material']['name'] ?? 'Materiau'),
            );
            return Response::text($dxf, 'application/dxf')
                ->withHeader('Content-Disposition', 'attachment; filename="facade_' . $facadeId . '.dxf"')
                ->withHeader('Cache-Control', 'no-cache, must-revalidate')
                ->withHeader('Expires', '0');
        });
    }
}
