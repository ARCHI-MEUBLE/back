<?php

declare(strict_types=1);

namespace App\Http\Guard;

use App\Domain\Shared\UnauthorizedException;
use App\Http\Request;
use App\Http\Session;

final class CustomerGuard implements GuardInterface
{
    public function check(Request $request, Session $session): void
    {
        if ($session->customerId() === null) {
            throw new UnauthorizedException('Non authentifié');
        }
    }
}
