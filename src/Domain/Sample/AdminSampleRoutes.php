<?php

declare(strict_types=1);

namespace App\Domain\Sample;

use App\Domain\Shared\NotFoundException;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\ImageUrl;

final class AdminSampleRoutes
{
    public function __construct(
        private readonly SampleService $service,
        private readonly SampleTypeRepository $types,
        private readonly SampleColorRepository $colors,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/samples', [new AdminGuard()])
            ->get(null, function (Request $r): Response {
                $data = $this->service->groupedForAdmin();
                foreach ($data as $i => $type) {
                    foreach ($type['colors'] as $j => $color) {
                        $data[$i]['colors'][$j]['image_url'] = ImageUrl::absolute($color['image_url'], $r->host);
                    }
                }
                return Response::json(['success' => true, 'data' => $data]);
            })
            ->post(null, fn(Request $r): Response => $this->dispatch($r));
    }

    private function dispatch(Request $r): Response
    {
        $input = $r->jsonOrEmpty();
        return match ($input['action'] ?? '') {
            'create_type' => $this->createType($input),
            'update_type' => $this->updateType($input),
            'delete_type' => $this->deleteType($input),
            'create_color' => $this->createColor($input),
            'update_color' => $this->updateColor($input),
            'delete_color' => $this->deleteColor($input),
            default => Response::json(['error' => 'action invalide'], 400),
        };
    }

    private function createType(array $input): Response
    {
        if (!isset($input['name'], $input['material']) || $input['name'] === '' || $input['material'] === '') {
            return Response::json(['error' => 'name et material requis'], 400);
        }
        $id = $this->types->create((string) $input['name'], (string) $input['material'], $input['description'] ?? null, (int) ($input['position'] ?? 0));
        return Response::json(['success' => true, 'id' => $id], 201);
    }

    private function updateType(array $input): Response
    {
        if (!isset($input['id']) || $input['id'] === '') {
            return Response::json(['error' => 'id requis'], 400);
        }
        if (!$this->types->update((int) $input['id'], $input)) {
            throw new NotFoundException('Type d\'échantillon non trouvé');
        }
        return Response::json(['success' => true]);
    }

    private function deleteType(array $input): Response
    {
        if (!isset($input['id']) || $input['id'] === '') {
            return Response::json(['error' => 'id requis'], 400);
        }
        $this->types->delete((int) $input['id']);
        return Response::json(['success' => true]);
    }

    private function createColor(array $input): Response
    {
        if (!isset($input['type_id'], $input['name']) || $input['type_id'] === '' || $input['name'] === '') {
            return Response::json(['error' => 'type_id et name requis'], 400);
        }
        if ($this->types->findById((int) $input['type_id']) === null) {
            throw new NotFoundException("Type d'échantillon non trouvé");
        }
        $id = $this->colors->create(
            (int) $input['type_id'],
            (string) $input['name'],
            $input['hex'] ?? null,
            $input['image_url'] ?? null,
            (int) ($input['position'] ?? 0),
            (float) ($input['price_per_m2'] ?? 0),
            (float) ($input['unit_price'] ?? 0),
        );
        return Response::json(['success' => true, 'id' => $id], 201);
    }

    private function updateColor(array $input): Response
    {
        if (!isset($input['id']) || $input['id'] === '') {
            return Response::json(['error' => 'id requis'], 400);
        }
        if (!$this->colors->update((int) $input['id'], $input)) {
            throw new NotFoundException('Couleur d\'échantillon non trouvée');
        }
        return Response::json(['success' => true]);
    }

    private function deleteColor(array $input): Response
    {
        if (!isset($input['id']) || $input['id'] === '') {
            return Response::json(['error' => 'id requis'], 400);
        }
        $this->colors->delete((int) $input['id']);
        return Response::json(['success' => true]);
    }
}
