<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

final class Route
{
    public function __construct(
        public readonly string $method,
        public readonly string $script,
        public readonly ?string $pattern,
        public readonly Closure $handler,
        public readonly array $guards,
        public readonly ErrorStyle $errorStyle,
    ) {}

    public function matchPathInfo(string $pathInfo): ?array
    {
        if ($this->pattern === null) {
            return $pathInfo === '' ? [] : null;
        }
        $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $this->pattern) . '$#';
        if (preg_match($regex, $pathInfo, $matches) !== 1) {
            return null;
        }
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }
}
