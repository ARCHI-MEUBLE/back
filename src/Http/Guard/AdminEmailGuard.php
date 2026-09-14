<?php

declare(strict_types=1);

namespace App\Http\Guard;

use App\Domain\Shared\ForbiddenException;
use App\Http\Request;
use App\Http\Session;

final class AdminEmailGuard implements GuardInterface
{
    public function check(Request $request, Session $session): void
    {
        if (!$session->has('admin_email')) {
            throw new ForbiddenException('Accès non autorisé');
        }
    }
}
