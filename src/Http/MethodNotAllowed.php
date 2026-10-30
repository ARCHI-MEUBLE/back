<?php

declare(strict_types=1);

namespace App\Http;

final class MethodNotAllowed
{
    public function __construct(public readonly ErrorStyle $errorStyle) {}
}
