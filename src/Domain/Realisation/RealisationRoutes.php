<?php

declare(strict_types=1);

namespace App\Domain\Realisation;

use App\Http\Response;
use App\Http\RouteCollection;

final class RealisationRoutes
{
    public function __construct(
        private readonly RealisationRepository $realisations,
        private readonly RealisationImageRepository $images,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('realisations')->get(null, function (): Response {
            $realisations = array_map(
                fn(array $r): array => RealisationPresenter::withImages($r, $this->images->forRealisation((int) $r['id'])),
                $this->realisations->all(),
            );
            return Response::json(['success' => true, 'realisations' => $realisations]);
        });
    }
}
