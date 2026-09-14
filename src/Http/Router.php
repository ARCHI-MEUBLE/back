<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    public function __construct(private readonly RouteCollection $routes) {}

    public static function endpoint(string $path): ?string
    {
        if ($path === '' || $path === 'health') {
            return 'health';
        }
        foreach (['backend/api/', 'api/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $endpoint = substr($path, strlen($prefix));
                return preg_replace('/\.php(\/|$)/', '$1', $endpoint) ?? $endpoint;
            }
        }
        return null;
    }

    public function resolve(string $path): ?array
    {
        $endpoint = self::endpoint($path);
        if ($endpoint === null) {
            return null;
        }
        $best = null;
        foreach ($this->routes->scriptNames() as $script) {
            if ($endpoint === $script || str_starts_with($endpoint, $script . '/')) {
                if ($best === null || strlen($script) > strlen($best)) {
                    $best = $script;
                }
            }
        }
        if ($best === null) {
            return null;
        }
        $rest = substr($endpoint, strlen($best));
        return [$best, rtrim($rest, '/')];
    }

    public function match(Request $request): RouteMatch|MethodNotAllowed|null
    {
        $resolved = $this->resolve($request->path);
        if ($resolved === null) {
            return null;
        }
        [$script, $pathInfo] = $resolved;
        $pathMatched = false;
        foreach ($this->orderedRoutes($script) as $route) {
            $params = $route->matchPathInfo($pathInfo);
            if ($params === null) {
                continue;
            }
            $pathMatched = true;
            if ($route->method === $request->method) {
                return new RouteMatch($route, $pathInfo, $params);
            }
        }
        if ($pathMatched || $pathInfo === '') {
            return new MethodNotAllowed($this->routes->errorStyle($script));
        }
        return null;
    }

    private function orderedRoutes(string $script): array
    {
        $routes = $this->routes->routesFor($script);
        usort($routes, static function (mixed $a, mixed $b): int {
            $aPattern = $a instanceof Route ? $a->pattern : null;
            $bPattern = $b instanceof Route ? $b->pattern : null;
            return ($aPattern === null ? 1 : 0) <=> ($bPattern === null ? 1 : 0);
        });
        return array_values(array_filter($routes, static fn(mixed $route): bool => $route instanceof Route));
    }
}
