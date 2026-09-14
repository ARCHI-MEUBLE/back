<?php

declare(strict_types=1);

namespace App\Http\Guard;

use App\Domain\Shared\UnauthorizedException;
use App\Http\Request;
use App\Http\Session;

final class AdminGuard implements GuardInterface
{
    public function __construct(private readonly string $message = 'Non authentifié') {}

    public function check(Request $request, Session $session): void
    {
        if (!$session->isAdmin()) {
            throw new UnauthorizedException($this->message);
        }
    }
}
