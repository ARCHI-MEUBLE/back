<?php

declare(strict_types=1);

namespace App\Http;

final class LegacyScriptHandler
{
    public function __construct(private readonly string $root) {}

    public function endpoint(string $path): string
    {
        return Router::endpoint($path) ?? $path;
    }

    public function specificity(string $endpoint): int
    {
        $matched = $this->locate($endpoint);
        return $matched === null ? 0 : strlen($matched[1]);
    }

    public function handle(string $path): bool
    {
        $endpoint = Router::endpoint($path);
        if ($endpoint === null || $endpoint === 'health') {
            return false;
        }
        $matched = $this->locate($endpoint);
        if ($matched === null) {
            return false;
        }
        [$script, $matchedEndpoint] = $matched;
        $pathInfo = substr($endpoint, strlen($matchedEndpoint));
        if ($pathInfo !== '') {
            $_SERVER['PATH_INFO'] = $pathInfo;
        }
        restore_error_handler();
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_httponly', '1');
        require_once $this->root . '/backend/core/Session.php';
        \Session::getInstance();
        require $script;
        return true;
    }

    private function locate(string $endpoint): ?array
    {
        $base = $this->root . '/backend/api/';
        $exact = $base . $endpoint . '.php';
        if (is_file($exact)) {
            return [$exact, $endpoint];
        }
        $parts = explode('/', $endpoint);
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $matchedEndpoint = implode('/', array_slice($parts, 0, $i));
            $candidate = $base . $matchedEndpoint . '.php';
            if (is_file($candidate)) {
                return [$candidate, $matchedEndpoint];
            }
        }
        return null;
    }
}
