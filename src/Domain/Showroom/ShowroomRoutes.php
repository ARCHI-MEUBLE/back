<?php

declare(strict_types=1);

namespace App\Domain\Showroom;

use App\Domain\Shared\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class ShowroomRoutes
{
    public function __construct(private readonly ShowroomRepository $showrooms) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('showrooms');
        $script->get(null, fn(): Response => Response::json($this->showrooms->all()));
        $script->get('/{id}', function (Request $r): Response {
            $showroom = $this->showrooms->findById((string) $r->param('id'));
            if ($showroom === null) {
                throw new NotFoundException('Showroom non trouvé');
            }
            return Response::json($showroom);
        });
    }
}
