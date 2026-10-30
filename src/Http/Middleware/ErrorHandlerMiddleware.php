<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\DomainException;
use App\Http\Request;
use App\Http\Response;
use App\Lib\Logger;
use Closure;
use Throwable;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Logger $logger) {}

    public function process(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            return $response instanceof Response ? $response : Response::empty(500);
        } catch (DomainException $exception) {
            return Response::json(['error' => $exception->getMessage()], $exception->status);
        } catch (Throwable $throwable) {
            $this->logger->error('unhandled exception', [
                'method' => $request->method,
                'path' => $request->path,
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ]);
            return Response::json(['error' => 'Erreur serveur'], 500);
        }
    }
}
