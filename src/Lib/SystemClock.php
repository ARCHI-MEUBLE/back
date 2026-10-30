<?php

declare(strict_types=1);

namespace App\Lib;

use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    public function microtime(): float
    {
        return microtime(true);
    }
}
