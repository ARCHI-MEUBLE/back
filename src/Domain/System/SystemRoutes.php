<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Lib\Clock;

final class SystemRoutes
{
    public function __construct(private readonly Clock $clock) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('health')->get(null, fn(Request $request, Session $session): Response => Response::json([
            'success' => true,
            'service' => 'ArchiMeuble Backend API',
            'version' => '1.0.0',
            'status' => 'healthy',
            'timestamp' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]));
    }
}
