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

final class FacadeDrillingTypeRoutes
{
    public function __construct(private readonly FacadeDrillingTypeRepository $types) {}

    public function register(RouteCollection $routes): void
    {
        $admin = [new AdminGuard()];
        $script = $routes->script('facade-drilling-types', errorStyle: ErrorStyle::Success);

        $script->get(null, fn(Request $r): Response => Response::json(['success' => true, 'data' => $this->types->all($r->queryString('active') !== null)]));
        $script->get('/{id}', function (Request $r): Response {
            $type = $this->types->findById((int) $r->param('id'));
            if ($type === null) {
                throw new NotFoundException('Type de perçage non trouvé');
            }
            return Response::json(['success' => true, 'data' => $type]);
        });

        $script->post(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['name'])) {
                throw new DomainException('Nom manquant');
            }
            $id = $this->types->create(
                (string) $data['name'],
                (string) ($data['description'] ?? ''),
                (string) ($data['icon_svg'] ?? ''),
                (float) ($data['price'] ?? 0),
                (bool) ($data['is_active'] ?? true),
            );
            return Response::json(['success' => true, 'data' => ['id' => $id]]);
        }, $admin);

        $script->put('/{id}', function (Request $r): Response {
            if ($this->types->findById((int) $r->param('id')) === null) {
                throw new NotFoundException('Type de perçage non trouvé');
            }
            $data = $r->json();
            $columns = [];
            foreach (['name', 'description', 'icon_svg', 'price', 'is_active'] as $field) {
                if (isset($data[$field])) {
                    $columns[$field] = $data[$field];
                }
            }
            if ($columns === []) {
                throw new DomainException('Aucune donnée à mettre à jour');
            }
            $this->types->update((int) $r->param('id'), $columns);
            return Response::json(['success' => true, 'message' => 'Type de perçage mis à jour']);
        }, $admin);

        $delete = function (Request $r): Response {
            $id = $r->param('id') ?? $r->queryString('id');
            if ($id === null) {
                throw new DomainException('ID manquant');
            }
            if ($this->types->findById((int) $id) === null) {
                throw new NotFoundException('Type de perçage non trouvé');
            }
            $this->types->delete((int) $id);
            return Response::json(['success' => true, 'message' => 'Type de perçage supprimé']);
        };
        $script->delete(null, $delete, $admin);
        $script->delete('/{id}', $delete, $admin);
    }
}
