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

    public function handle(string $path): bool
    {
        $endpoint = Router::endpoint($path);
        if ($endpoint === null || $endpoint === 'health') {
            return false;
        }
        $script = $this->locate($endpoint);
        if ($script === null) {
            return false;
        }
        restore_error_handler();
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_httponly', '1');
        require_once $this->root . '/backend/core/Session.php';
        \Session::getInstance();
        require $script;
        return true;
    }

    private function locate(string $endpoint): ?string
    {
        $base = $this->root . '/backend/api/';
        $exact = $base . $endpoint . '.php';
        if (is_file($exact)) {
            return $exact;
        }
        $parts = explode('/', $endpoint);
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $candidate = $base . implode('/', array_slice($parts, 0, $i)) . '.php';
            if (is_file($candidate)) {
                $_SERVER['PATH_INFO'] = '/' . implode('/', array_slice($parts, $i));
                return $candidate;
            }
        }
        return null;
    }
}
