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

final class FacadeMaterialRoutes
{
    public function __construct(private readonly FacadeMaterialRepository $materials) {}

    public function register(RouteCollection $routes): void
    {
        $admin = [new AdminGuard()];
        $script = $routes->script('facade-materials', errorStyle: ErrorStyle::Success);

        $script->get(null, fn(Request $r): Response => Response::json(['success' => true, 'data' => $this->materials->all($r->queryString('active') !== null)]));
        $script->get('/{id}', function (Request $r): Response {
            $material = $this->materials->findById((int) $r->param('id'));
            if ($material === null) {
                throw new NotFoundException('Matériau non trouvé');
            }
            return Response::json(['success' => true, 'data' => $material]);
        });

        $script->post(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['name'])) {
                throw new DomainException('Nom manquant');
            }
            [$colorHex, $textureUrl] = FacadeMaterialInput::colorAndTexture($data);
            $id = $this->materials->create([
                $data['name'], $colorHex, $textureUrl, $data['price_modifier'] ?? 0, $data['price_per_m2'] ?? 150,
                FacadeMaterialInput::isActive($data),
            ]);
            return Response::json(['success' => true, 'data' => ['id' => $id]]);
        }, $admin);

        $script->put('/{id}', function (Request $r): Response {
            if ($this->materials->findById((int) $r->param('id')) === null) {
                throw new NotFoundException('Matériau non trouvé');
            }
            $data = $r->json();
            [$colorHex, $textureUrl] = FacadeMaterialInput::colorAndTexture($data);
            $columns = ['color_hex' => $colorHex, 'texture_url' => $textureUrl];
            if (isset($data['name'])) {
                $columns = ['name' => $data['name']] + $columns;
            }
            foreach (['price_modifier', 'price_per_m2'] as $field) {
                if (isset($data[$field])) {
                    $columns[$field] = $data[$field];
                }
            }
            if (isset($data['is_active'])) {
                $columns['is_active'] = FacadeMaterialInput::isActive($data);
            }
            $this->materials->update((int) $r->param('id'), $columns);
            return Response::json(['success' => true, 'message' => 'Matériau mis à jour']);
        }, $admin);

        $delete = function (Request $r): Response {
            $id = $r->param('id') ?? $r->queryString('id');
            if ($id === null) {
                throw new DomainException('ID manquant');
            }
            if ($this->materials->findById((int) $id) === null) {
                throw new NotFoundException('Matériau non trouvé');
            }
            $this->materials->delete((int) $id);
            return Response::json(['success' => true, 'message' => 'Matériau supprimé']);
        };
        $script->delete(null, $delete, $admin);
        $script->delete('/{id}', $delete, $admin);
    }
}
