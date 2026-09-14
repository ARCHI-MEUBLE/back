<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class FacadeRoutes
{
    public function __construct(private readonly FacadeRepository $facades) {}

    public function register(RouteCollection $routes): void
    {
        $admin = [new AdminGuard()];
        $script = $routes->script('facades', errorStyle: ErrorStyle::Success);

        $script->get(null, fn(Request $r): Response => Response::json(['success' => true, 'data' => $this->facades->all($r->queryString('active') !== null)]));
        $script->get('/{id}', function (Request $r): Response {
            $facade = $this->facades->findById((int) $r->param('id'));
            if ($facade === null) {
                throw new NotFoundException('Façade non trouvée');
            }
            return Response::json(['success' => true, 'data' => $facade]);
        });

        $script->post(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['name'], $data['width'], $data['height'], $data['depth'])) {
                throw new DomainException('Données manquantes');
            }
            $id = $this->facades->create([
                $data['name'], $data['description'] ?? '', $data['width'], $data['height'], $data['depth'],
                $data['base_price'] ?? 0, $data['image_url'] ?? '', FacadeMaterialInput::isActive($data),
            ]);
            return Response::json(['success' => true, 'data' => ['id' => $id]]);
        }, $admin);

        $script->put('/{id}', function (Request $r): Response {
            $data = $r->json();
            $columns = [];
            foreach (['name', 'description', 'width', 'height', 'depth', 'base_price', 'image_url'] as $field) {
                if (isset($data[$field])) {
                    $columns[$field] = $data[$field];
                }
            }
            if (isset($data['is_active'])) {
                $columns['is_active'] = FacadeMaterialInput::isActive($data);
            }
            if ($columns === []) {
                throw new DomainException('Aucune donnée à mettre à jour');
            }
            $this->facades->update((int) $r->param('id'), $columns);
            return Response::json(['success' => true, 'message' => 'Façade mise à jour']);
        }, $admin);

        $script->delete('/{id}', function (Request $r): Response {
            $this->facades->delete((int) $r->param('id'));
            return Response::json(['success' => true, 'message' => 'Façade supprimée']);
        }, $admin);
    }
}
