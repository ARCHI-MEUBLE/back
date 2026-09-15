<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\CorsSettings;
use App\Http\Request;
use App\Http\Response;
use Closure;

final class CorsMiddleware implements MiddlewareInterface
{
    private const METHODS = 'GET, POST, PUT, DELETE, OPTIONS';
    private const HEADERS = 'Content-Type, Authorization, X-Requested-With, Stripe-Signature, X-Calendly-Webhook-Signature';

    public function __construct(private readonly CorsSettings $settings) {}

    public function process(Request $request, Closure $next): Response
    {
        $origin = $request->origin();
        $allowed = $this->settings->allows($origin);
        if ($request->isPreflight()) {
            return $this->decorate(Response::empty(204), $origin, $allowed)
                ->withHeader('Access-Control-Max-Age', '86400');
        }
        $response = $next($request);
        return $this->decorate($response instanceof Response ? $response : Response::empty(500), $origin, $allowed);
    }

    private function decorate(Response $response, string $origin, bool $allowed): Response
    {
        $response = $response
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Methods', self::METHODS)
            ->withHeader('Access-Control-Allow-Headers', self::HEADERS);
        if (!$allowed) {
            return $response;
        }
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
