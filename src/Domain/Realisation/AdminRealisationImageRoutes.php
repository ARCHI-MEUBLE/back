<?php

declare(strict_types=1);

namespace App\Domain\Realisation;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminRealisationImageRoutes
{
    public function __construct(private readonly RealisationImageRepository $images) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/realisation-images', [new AdminGuard('Non autorisé')], ErrorStyle::Message)
            ->get(null, function (Request $r): Response {
                $realisationId = (int) ($r->queryString('realisation_id') ?? 0);
                if ($realisationId <= 0) {
                    throw new DomainException('ID de réalisation requis');
                }
                return Response::json(['success' => true, 'images' => $this->images->forRealisation($realisationId)]);
            })
            ->post(null, function (Request $r): Response {
                $input = $r->json();
                $realisationId = (int) ($input['realisation_id'] ?? 0);
                $imageUrl = trim((string) ($input['image_url'] ?? ''));
                if ($realisationId <= 0 || $imageUrl === '') {
                    throw new DomainException('Données invalides');
                }
                $ordre = (int) ($input['ordre'] ?? 0);
                $ordre = $ordre === 0 ? $this->images->nextOrder($realisationId) : $ordre;
                $id = $this->images->create($realisationId, $imageUrl, trim((string) ($input['legende'] ?? '')), $ordre);
                return Response::json(['success' => true, 'id' => (string) $id, 'message' => 'Image ajoutée avec succès']);
            })
            ->put(null, function (Request $r): Response {
                $input = $r->json();
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) {
                    throw new DomainException('ID invalide');
                }
                $columns = self::updateColumns($input);
                if ($columns === []) {
                    throw new DomainException('Aucune donnée à mettre à jour');
                }
                $this->images->update($id, $columns);
                return Response::json(['success' => true, 'message' => 'Image mise à jour']);
            })
            ->delete(null, function (Request $r): Response {
                $id = (int) ($r->queryString('id') ?? 0);
                if ($id <= 0) {
                    throw new DomainException('ID invalide');
                }
                $this->images->delete($id);
                return Response::json(['success' => true, 'message' => 'Image supprimée']);
            });
    }

    private static function updateColumns(array $input): array
    {
        $columns = [];
        $imageUrl = trim((string) ($input['image_url'] ?? ''));
        if ($imageUrl !== '') {
            $columns['image_url'] = $imageUrl;
        }
        if (isset($input['legende'])) {
            $columns['legende'] = trim((string) $input['legende']);
        }
        $ordre = (int) ($input['ordre'] ?? 0);
        if ($ordre > 0) {
            $columns['ordre'] = $ordre;
        }
        return $columns;
    }
}
