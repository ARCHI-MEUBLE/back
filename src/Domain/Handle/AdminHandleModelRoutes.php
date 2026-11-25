<?php

declare(strict_types=1);

namespace App\Domain\Handle;

use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\ImageUrl;

final class AdminHandleModelRoutes
{
    public function __construct(
        private readonly HandleModelService $service,
        private readonly HandleModelRepository $models,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/handle-models', [new AdminGuard()], ErrorStyle::Success)
            ->get(null, fn(Request $r): Response => Response::json([
                'success' => true,
                'data' => array_map(static fn(array $model): array => ImageUrl::decorate($model, $r->host), $this->models->all()),
            ]))
            ->post(null, fn(Request $r): Response => $this->dispatch($r->jsonOrEmpty()));
    }

    private function dispatch(array $input): Response
    {
        $action = $input['action'] ?? '';
        if ($action === 'create') {
            return Response::json(['success' => true, 'id' => $this->service->create($input)], 201);
        }
        if ($action === 'update') {
            $this->service->update($input);
        } elseif ($action === 'delete') {
            $this->service->delete($input);
        } else {
            return Response::json(['success' => false, 'error' => 'Action invalide'], 400);
        }
        return Response::json(['success' => true]);
    }
}
