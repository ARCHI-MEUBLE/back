<?php

declare(strict_types=1);

namespace App\Http\Guard;

use App\Http\Request;
use App\Http\Session;

interface GuardInterface
{
    public function check(Request $request, Session $session): void;
}
