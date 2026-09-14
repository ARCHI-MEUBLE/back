<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

final class LegacyActionRouter
{
    public static function register(RouteCollection $routes, string $script, array $guards, Closure $dispatch): void
    {
        $scriptRoutes = $routes->script($script, $guards);
        $handler = static fn(Request $request, Session $session): Response => $dispatch($request->method, (string) $request->param('action'), $request, $session);
        foreach ([null, '/{action}'] as $pattern) {
            $scriptRoutes->get($pattern, $handler)->post($pattern, $handler)->put($pattern, $handler)->delete($pattern, $handler);
        }
    }
}
