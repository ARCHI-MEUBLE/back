<?php

declare(strict_types=1);

namespace App\Http;

final class RouteMatch
{
    public function __construct(
        public readonly Route $route,
        public readonly string $pathInfo,
        public readonly array $params,
    ) {}
}
