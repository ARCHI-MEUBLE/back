<?php

declare(strict_types=1);

namespace App\Domain\Category;

use App\Domain\Shared\DomainException;
use App\Http\Guard\AdminSessionForbiddenGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class CategoryRoutes
{
    public function __construct(private readonly CategoryService $service) {}

    public function register(RouteCollection $routes): void
    {
        $admin = [new AdminSessionForbiddenGuard()];
        $script = $routes->script('categories');

        $script->get(null, function (Request $r): Response {
            if ($r->queryString('id') !== null) {
                return Response::json(CategoryImageUrl::decorate($this->service->find((int) $r->queryString('id')), $r->host));
            }
            $categories = array_map(
                fn(array $c): array => CategoryImageUrl::decorate($c, $r->host),
                $this->service->list($r->queryString('active') === 'true'),
            );
            return Response::json(['categories' => $categories]);
        });

        $script->post(null, function (Request $r): Response {
            $input = $r->json();
            if (!isset($input['name'])) {
                throw new DomainException('Nom requis');
            }
            $created = $this->service->create(
                (string) $input['name'],
                isset($input['slug']) ? (string) $input['slug'] : null,
                self::stringOrNull($input, 'description'),
                self::stringOrNull($input, 'image_url') ?? self::stringOrNull($input, 'imageUrl'),
                (int) ($input['display_order'] ?? $input['displayOrder'] ?? 0),
                self::boolOption($input, 'is_active') ?? self::boolOption($input, 'isActive') ?? true,
            );
            return Response::json(['success' => true, 'category' => CategoryImageUrl::decorate($created, $r->host)], 201);
        }, $admin);

        $script->put(null, function (Request $r): Response {
            $input = $r->json();
            if (($input['action'] ?? null) === 'reorder') {
                $ids = $input['categoryIds'] ?? null;
                if (!is_array($ids)) {
                    throw new DomainException('IDs des catégories requis pour le réordonnancement');
                }
                $this->service->reorder($ids);
                return Response::json(['success' => true]);
            }
            if (!isset($input['id'])) {
                throw new DomainException('ID de la catégorie requis');
            }
            $data = self::updatePayload($input);
            if ($data === []) {
                throw new DomainException('Aucune donnée à mettre à jour');
            }
            $updated = $this->service->update((int) $input['id'], $data);
            return Response::json(['success' => true, 'category' => CategoryImageUrl::decorate($updated, $r->host)]);
        }, $admin);

        $script->delete(null, function (Request $r): Response {
            $id = $r->queryString('id') ?? $r->json()['id'] ?? null;
            if ($id === null) {
                throw new DomainException('ID de la catégorie requis');
            }
            $this->service->delete((int) $id);
            return Response::json(['success' => true]);
        }, $admin);
    }

    private static function stringOrNull(array $input, string $key): ?string
    {
        return isset($input[$key]) ? (string) $input[$key] : null;
    }

    private static function boolOption(array $input, string $key): ?bool
    {
        if (!isset($input[$key]) || $input[$key] === '') {
            return null;
        }
        return filter_var($input[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private static function updatePayload(array $input): array
    {
        if (isset($input['imageUrl'])) {
            $input['image_url'] = $input['imageUrl'];
        }
        if (isset($input['displayOrder'])) {
            $input['display_order'] = $input['displayOrder'];
        }
        if (isset($input['isActive'])) {
            $input['is_active'] = $input['isActive'] === '' ? true : filter_var($input['isActive'], FILTER_VALIDATE_BOOLEAN);
        }
        $data = [];
        foreach (['name', 'slug', 'description', 'image_url', 'display_order', 'is_active'] as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }
        return $data;
    }
}
