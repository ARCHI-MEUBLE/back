<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminCatalogueRoutes
{
    public function __construct(private readonly CatalogueRepository $catalogue) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('admin/catalogue', [new AdminGuard('Authentification requise')], ErrorStyle::Success);
        $script->get(null, fn(Request $r): Response => $this->get($r));
        $script->post(null, fn(Request $r): Response => $this->post($r));
        $script->put(null, fn(Request $r): Response => $this->put($r));
        $script->delete(null, fn(Request $r): Response => $this->delete($r));
    }

    private function get(Request $r): Response
    {
        return match ($r->queryString('action') ?? 'list') {
            'list' => Response::json(['success' => true, 'data' => $this->catalogue->adminList($r->queryString('category'), $r->queryString('search'), $r->queryString('available'))]),
            'item' => $this->item($r),
            'categories' => Response::json(['success' => true, 'data' => $this->catalogue->adminCategories()]),
            'materials' => Response::json(['success' => true, 'data' => CatalogueMapper::MATERIALS]),
            default => self::unsupported(),
        };
    }

    private function item(Request $r): Response
    {
        $id = $r->queryString('id');
        if ($id === null) {
            return Response::json(['success' => false, 'error' => 'ID requis'], 400);
        }
        $item = $this->catalogue->findById((int) $id);
        return $item === null
            ? Response::json(['success' => false, 'error' => 'Article non trouvé'], 404)
            : Response::json(['success' => true, 'data' => $item]);
    }

    private function post(Request $r): Response
    {
        if (($r->queryString('action') ?? 'list') !== 'create') {
            return self::unsupported();
        }
        $data = $r->jsonOrEmpty();
        if ($data === []) {
            return Response::json(['success' => false, 'error' => 'Données JSON requises'], 400);
        }
        foreach (['name', 'category', 'unit_price'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return Response::json(['success' => false, 'error' => "Champ requis manquant: $field"], 400);
            }
        }
        $id = $this->catalogue->create(CatalogueItemInput::values($data));
        return Response::json(['success' => true, 'message' => 'Article ajouté avec succès', 'id' => $id], 201);
    }

    private function put(Request $r): Response
    {
        if (($r->queryString('action') ?? 'list') !== 'update') {
            return self::unsupported();
        }
        $id = $r->queryString('id');
        if ($id === null) {
            return Response::json(['success' => false, 'error' => 'ID requis'], 400);
        }
        if ($this->catalogue->findById((int) $id) === null) {
            return Response::json(['success' => false, 'error' => 'Article non trouvé'], 404);
        }
        $data = $r->jsonOrEmpty();
        if ($data === []) {
            return Response::json(['success' => false, 'error' => 'Données JSON requises'], 400);
        }
        $this->catalogue->update((int) $id, CatalogueItemInput::values($data));
        return Response::json(['success' => true, 'message' => 'Article mis à jour avec succès']);
    }

    private function delete(Request $r): Response
    {
        if (($r->queryString('action') ?? 'list') !== 'delete') {
            return self::unsupported();
        }
        $id = $r->queryString('id');
        if ($id === null) {
            return Response::json(['success' => false, 'error' => 'ID requis'], 400);
        }
        return $this->catalogue->delete((int) $id)
            ? Response::json(['success' => true, 'message' => 'Article supprimé avec succès'])
            : Response::json(['success' => false, 'error' => 'Article non trouvé'], 404);
    }

    private static function unsupported(): Response
    {
        return Response::json(['success' => false, 'error' => 'Action non supportée'], 400);
    }
}
