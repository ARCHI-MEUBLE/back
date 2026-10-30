<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Shared\DomainException;
use App\Http\Guard\GuardInterface;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Middleware\SessionMiddleware;
use Closure;

final class Kernel
{
    public function __construct(
        private readonly Router $router,
        private readonly StaticFileHandler $static,
        private readonly array $middleware,
    ) {}

    public function handle(Request $request): ?Response
    {
        $static = $this->static->handle($request);
        if ($static !== null) {
            return $static;
        }
        $match = $this->router->match($request);
        if ($match === null) {
            return null;
        }
        $handler = function (Request $request) use ($match): Response {
            if ($match instanceof MethodNotAllowed) {
                return Response::json($match->errorStyle->payload('Méthode non autorisée'), 405);
            }
            return $this->dispatch($request, $match);
        };
        return $this->pipeline($handler)($request);
    }

    private function dispatch(Request $request, RouteMatch $match): Response
    {
        $request = $request->withMatch($match->pathInfo, $match->params);
        $session = SessionMiddleware::current();
        try {
            foreach ($match->route->guards as $guard) {
                if ($guard instanceof GuardInterface) {
                    $guard->check($request, $session);
                }
            }
            return ($match->route->handler)($request, $session);
        } catch (DomainException $exception) {
            return Response::json($match->route->errorStyle->payload($exception->getMessage()), $exception->status);
        }
    }

    private function pipeline(Closure $core): Closure
    {
        $next = $core;
        foreach (array_reverse($this->middleware) as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                continue;
            }
            $current = $next;
            $next = static fn(Request $request): Response => $middleware->process($request, $current);
        }
        return $next;
    }
}
