<?php

declare(strict_types=1);

namespace App\Domain\Handle;

use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\ImageUrl;

final class HandleModelRoutes
{
    public function __construct(private readonly HandleModelRepository $models) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('handle-models', errorStyle: ErrorStyle::Success)->get(null, fn(Request $r): Response => Response::json([
            'success' => true,
            'data' => array_map(static fn(array $model): array => ImageUrl::decorate($model, $r->host), $this->models->active()),
        ]));
    }
}
