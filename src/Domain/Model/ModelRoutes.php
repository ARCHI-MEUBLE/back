<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Shared\DomainException;
use App\Http\Guard\AdminSessionForbiddenGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\ImageUrl;

final class ModelRoutes
{
    private const IMAGE_KEYS = ['image_url', 'hover_image_url'];

    public function __construct(private readonly ModelService $service) {}

    public function register(RouteCollection $routes): void
    {
        $admin = [new AdminSessionForbiddenGuard()];
        $script = $routes->script('models');

        $script->get(null, function (Request $r): Response {
            if ($r->queryString('id') !== null) {
                return Response::json(ImageUrl::decorate($this->service->find((int) $r->queryString('id')), $r->host, self::IMAGE_KEYS));
            }
            $models = array_map(fn(array $m): array => ImageUrl::decorate($m, $r->host, self::IMAGE_KEYS), $this->service->list());
            return Response::json(['models' => $models]);
        });

        $script->post(null, function (Request $r): Response {
            $created = $this->service->create($r->json());
            return Response::json(['success' => true, 'model' => ImageUrl::decorate($created, $r->host, self::IMAGE_KEYS)], 201);
        }, $admin);

        $script->put(null, function (Request $r): Response {
            $id = $r->queryString('id') ?? $r->json()['id'] ?? null;
            if ($id === null) {
                throw new DomainException('ID du modèle requis');
            }
            $updated = $this->service->update((int) $id, $r->json());
            return Response::json(['success' => true, 'model' => ImageUrl::decorate($updated, $r->host, self::IMAGE_KEYS)]);
        }, $admin);

        $script->delete(null, function (Request $r): Response {
            $id = $r->queryString('id') ?? $r->json()['id'] ?? null;
            if ($id === null) {
                throw new DomainException('ID du modèle requis');
            }
            $this->service->delete((int) $id);
            return Response::json(['success' => true]);
        }, $admin);
    }
}
