<?php

declare(strict_types=1);

namespace App\Lib;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;

    public function microtime(): float;
}
