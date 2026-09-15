<?php

declare(strict_types=1);

namespace App\Domain\Realisation;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminRealisationRoutes
{
    public function __construct(private readonly RealisationRepository $realisations) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/realisations', [new AdminGuard('Non autorisé')], ErrorStyle::Message)
            ->get(null, function (Request $r): Response {
                $data = $r->queryString('id') !== null
                    ? $this->realisations->findById((int) $r->queryString('id'))
                    : $this->realisations->all();
                return Response::json(['success' => true, 'realisations' => $data]);
            })
            ->post(null, function (Request $r): Response {
                $input = $r->json();
                if (!isset($input['titre']) || $input['titre'] === '') {
                    throw new DomainException('Le titre est obligatoire');
                }
                return Response::json(['success' => true, 'id' => $this->realisations->create($input), 'message' => 'Réalisation créée']);
            })
            ->put(null, function (Request $r): Response {
                $input = $r->json();
                if (!isset($input['id']) || $input['id'] === '' || $input['id'] === 0) {
                    throw new DomainException('ID manquant');
                }
                $id = (int) $input['id'];
                unset($input['id']);
                if ($this->realisations->findById($id) === null) {
                    throw new NotFoundException('Réalisation non trouvée');
                }
                if (!$this->realisations->update($id, $input)) {
                    throw new DomainException('Aucun champ à mettre à jour');
                }
                return Response::json(['success' => true, 'message' => 'Réalisation mise à jour']);
            })
            ->delete(null, function (Request $r): Response {
                $id = $r->queryString('id');
                if ($id === null) {
                    throw new DomainException('ID manquant');
                }
                $this->realisations->delete((int) $id);
                return Response::json(['success' => true, 'message' => 'Réalisation supprimée']);
            });
    }
}
