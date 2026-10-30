<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\SessionSettings;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use Closure;
use LogicException;

final class SessionMiddleware implements MiddlewareInterface
{
    private static ?Session $current = null;

    public function __construct(private readonly SessionSettings $settings) {}

    public static function current(): Session
    {
        if (self::$current === null) {
            throw new LogicException('Session middleware has not run');
        }
        return self::$current;
    }

    public function process(Request $request, Closure $next): Response
    {
        $session = new Session($this->settings);
        $session->start($request->host);
        self::$current = $session;
        $response = $next($request);
        return $response instanceof Response ? $response : Response::empty(500);
    }
}
