<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Lib\Clock;
use App\Lib\Logger;
use Closure;

final class RequestLogMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Logger $logger, private readonly Clock $clock) {}

    public function process(Request $request, Closure $next): Response
    {
        $started = $this->clock->microtime();
        $response = $next($request);
        $response = $response instanceof Response ? $response : Response::empty(500);
        $this->logger->info('request', [
            'method' => $request->method,
            'path' => '/' . $request->path,
            'status' => $response->status,
            'duration_ms' => (int) round(($this->clock->microtime() - $started) * 1000),
        ]);
        return $response;
    }
}
