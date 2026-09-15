<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class CatalogueRoutes
{
    public function __construct(
        private readonly CatalogueRepository $catalogue,
        private readonly CatalogueVariationRepository $variations,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('catalogue', errorStyle: ErrorStyle::Success)->get(null, function (Request $r): Response {
            return match ($r->queryString('action') ?? 'list') {
                'list' => $this->list($r),
                'item' => $this->item($r),
                'categories' => Response::json(['success' => true, 'data' => $this->catalogue->publicCategories()]),
                'materials' => Response::json(['success' => true, 'data' => CatalogueMapper::MATERIALS]),
                default => Response::json(['success' => false, 'error' => 'Action non supportée'], 400),
            };
        });
    }

    private function list(Request $r): Response
    {
        $category = $r->queryString('category');
        $search = $r->queryString('search');
        $limit = (int) ($r->queryString('limit') ?? 50);
        $offset = (int) ($r->queryString('offset') ?? 0);
        $items = $this->catalogue->publicList($category, $search, $limit, $offset);
        if ($items !== []) {
            $byItem = CatalogueMapper::groupByItem($this->variations->forItems(array_column($items, 'id')));
            $items = CatalogueMapper::withVariations($items, $byItem);
        }
        $total = $this->catalogue->publicCount($category, $search);
        return Response::json([
            'success' => true,
            'data' => $items,
            'pagination' => ['total' => $total, 'limit' => $limit, 'offset' => $offset, 'has_more' => ($offset + $limit) < $total],
        ]);
    }

    private function item(Request $r): Response
    {
        $id = $r->queryString('id');
        if ($id === null) {
            return Response::json(['success' => false, 'error' => 'ID requis'], 400);
        }
        $item = $this->catalogue->findPublicById((int) $id);
        if ($item === null) {
            return Response::json(['success' => false, 'error' => 'Article non trouvé'], 404);
        }
        $item['variations'] = array_map(CatalogueMapper::variation(...), $this->variations->forItem((int) $id));
        return Response::json(['success' => true, 'data' => $item]);
    }
}
