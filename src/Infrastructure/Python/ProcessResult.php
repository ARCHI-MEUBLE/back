<?php

declare(strict_types=1);

namespace App\Infrastructure\Python;

final class ProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
        public readonly float $durationSeconds,
        public readonly bool $timedOut,
    ) {}

    public function succeeded(): bool
    {
        return !$this->timedOut && $this->exitCode === 0;
    }
}
