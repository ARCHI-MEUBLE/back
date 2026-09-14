<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminCatalogueVariationRoutes
{
    public function __construct(private readonly CatalogueVariationRepository $variations) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('admin/catalogue-variations', [new AdminGuard('Authentification requise')], ErrorStyle::Success);
        $script->get(null, fn(Request $r): Response => $this->list($r));
        $script->post(null, fn(Request $r): Response => $this->add($r));
        $script->delete(null, fn(Request $r): Response => $this->delete($r));
    }

    private function list(Request $r): Response
    {
        if (($r->queryString('action') ?? 'list') !== 'list') {
            return self::unsupported();
        }
        $itemId = $r->queryString('item_id');
        if ($itemId === null) {
            return Response::json(['success' => false, 'error' => 'item_id requis'], 400);
        }
        return Response::json(['success' => true, 'data' => array_map(CatalogueMapper::variation(...), $this->variations->forItem((int) $itemId))]);
    }

    private function add(Request $r): Response
    {
        if (($r->queryString('action') ?? 'list') !== 'add') {
            return self::unsupported();
        }
        $data = $r->jsonOrEmpty();
        if (!isset($data['catalogue_item_id'], $data['color_name'], $data['image_url'])) {
            return Response::json(['success' => false, 'error' => 'Paramètres manquants'], 400);
        }
        $itemId = (int) $data['catalogue_item_id'];
        $colorName = (string) $data['color_name'];
        if ($this->variations->existsForColor($itemId, $colorName)) {
            return Response::json(['success' => false, 'error' => 'Cette variation existe déjà pour cet article'], 400);
        }
        $isDefault = (isset($data['is_default']) && $data['is_default'] !== '') ? filter_var($data['is_default'], FILTER_VALIDATE_BOOLEAN) : false;
        $id = $this->variations->create($itemId, $colorName, (string) $data['image_url'], $isDefault);
        return Response::json(['success' => true, 'message' => 'Variation ajoutée avec succès', 'id' => $id], 201);
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
        return $this->variations->delete((int) $id)
            ? Response::json(['success' => true, 'message' => 'Variation supprimée avec succès'])
            : Response::json(['success' => false, 'error' => 'Variation non trouvée'], 404);
    }

    private static function unsupported(): Response
    {
        return Response::json(['success' => false, 'error' => 'Action non supportée'], 400);
    }
}
