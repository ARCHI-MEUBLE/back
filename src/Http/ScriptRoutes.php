<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

final class ScriptRoutes
{
    public function __construct(
        private readonly RouteCollection $collection,
        private readonly string $script,
        private readonly array $guards,
        private readonly ErrorStyle $errorStyle,
    ) {}

    public function get(?string $pattern, Closure $handler, ?array $guards = null): self
    {
        return $this->add('GET', $pattern, $handler, $guards);
    }

    public function post(?string $pattern, Closure $handler, ?array $guards = null): self
    {
        return $this->add('POST', $pattern, $handler, $guards);
    }

    public function put(?string $pattern, Closure $handler, ?array $guards = null): self
    {
        return $this->add('PUT', $pattern, $handler, $guards);
    }

    public function delete(?string $pattern, Closure $handler, ?array $guards = null): self
    {
        return $this->add('DELETE', $pattern, $handler, $guards);
    }

    private function add(string $method, ?string $pattern, Closure $handler, ?array $guards): self
    {
        $this->collection->add(new Route($method, $this->script, $pattern, $handler, $guards ?? $this->guards, $this->errorStyle));
        return $this;
    }
}
