<?php

declare(strict_types=1);

namespace App\Http;

final class RouteCollection
{
    private array $routes = [];
    private array $scripts = [];

    public function script(string $name, array $guards = [], ErrorStyle $errorStyle = ErrorStyle::Plain): ScriptRoutes
    {
        $this->scripts[$name] = $errorStyle;
        return new ScriptRoutes($this, $name, $guards, $errorStyle);
    }

    public function add(Route $route): void
    {
        $this->routes[$route->script][] = $route;
    }

    public function scriptNames(): array
    {
        return array_keys($this->scripts);
    }

    public function errorStyle(string $script): ErrorStyle
    {
        $style = $this->scripts[$script] ?? ErrorStyle::Plain;
        return $style instanceof ErrorStyle ? $style : ErrorStyle::Plain;
    }

    public function routesFor(string $script): array
    {
        $routes = $this->routes[$script] ?? [];
        return is_array($routes) ? $routes : [];
    }
}
