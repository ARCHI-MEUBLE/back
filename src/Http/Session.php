<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\SessionSettings;

final class Session
{
    public function __construct(private readonly SessionSettings $settings) {}

    public function start(string $host): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->settings->secureFor($host),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name(SessionSettings::COOKIE_NAME);
        session_start();
        $last = $_SESSION['_last_regeneration'] ?? null;
        if (!is_int($last)) {
            $_SESSION['_last_regeneration'] = time();
        } elseif (time() - $last > $this->settings->regenerationInterval) {
            $this->regenerate();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function string(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function int(string $key): ?int
    {
        $value = $_SESSION[$key] ?? null;
        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_last_regeneration'] = time();
    }

    public function destroy(): void
    {
        $_SESSION = [];
        $name = session_name();
        if (ini_get('session.use_cookies') === '1' && is_string($name)) {
            $params = session_get_cookie_params();
            setcookie($name, '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
        session_destroy();
    }

    public function isAdmin(): bool
    {
        return $this->has('admin_email') && $this->get('is_admin') === true;
    }

    public function customerId(): ?int
    {
        return $this->int('customer_id');
    }
}
