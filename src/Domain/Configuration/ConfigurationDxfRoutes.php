<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class ConfigurationDxfRoutes
{
    public function __construct(
        private readonly Connection $db,
        private readonly string $rootDir,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('files/dxf')->get(null, function (Request $r, Session $s): Response {
            if (!$s->isAdmin()) {
                throw new UnauthorizedException();
            }
            $configId = $r->queryString('id');
            if ($configId === null) {
                throw new DomainException('ID de configuration requis');
            }
            $path = $this->resolve($configId);
            return Response::file($path, 'application/dxf', 'configuration_' . $configId . '.dxf')
                ->withHeader('Cache-Control', 'no-cache, must-revalidate')
                ->withHeader('Expires', '0');
        });
    }

    private function resolve(string $configId): string
    {
        $config = $this->db->queryOne('SELECT id, dxf_url, prompt FROM configurations WHERE id = ?', [(int) $configId]);
        $dxfUrl = $config['dxf_url'] ?? null;
        if (is_string($dxfUrl) && $dxfUrl !== '') {
            $found = $this->findSpecific($dxfUrl);
            if ($found !== null) {
                return $found;
            }
        }
        $fallback = $this->rootDir . '/backend/python/pieces/piece_general.dxf';
        if (!is_file($fallback)) {
            throw new NotFoundException('DXF file not found. Please regenerate the configuration.');
        }
        return $fallback;
    }

    private function findSpecific(string $dxfUrl): ?string
    {
        $cleanPath = (string) parse_url($dxfUrl, PHP_URL_PATH);
        $parent = dirname($this->rootDir);
        foreach (['/data' . $cleanPath, '/app' . $cleanPath, $parent . '/front/public' . $cleanPath, $this->rootDir . $cleanPath, $parent . '/public' . $cleanPath, $this->rootDir . '/backend' . $cleanPath] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}
