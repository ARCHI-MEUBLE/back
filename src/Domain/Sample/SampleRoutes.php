<?php

declare(strict_types=1);

namespace App\Domain\Sample;

use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\ImageUrl;

final class SampleRoutes
{
    public function __construct(private readonly SampleService $service) {}

    public function register(RouteCollection $routes): void
    {
        $handler = fn(Request $r): Response => Response::json(['success' => true, 'materials' => $this->decorate($this->service->groupedByMaterialForCustomers(), $r->host)]);
        $routes->script('samples/index')->get(null, $handler);
        $routes->script('samples')->get(null, $handler);
    }

    private function decorate(array $grouped, string $host): array
    {
        foreach ($grouped as $material => $types) {
            foreach ($types as $i => $type) {
                foreach ($type['colors'] as $j => $color) {
                    $grouped[$material][$i]['colors'][$j]['image_url'] = ImageUrl::absolute($color['image_url'], $host);
                }
            }
        }
        return $grouped;
    }
}
