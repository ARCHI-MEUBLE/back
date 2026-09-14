<?php

declare(strict_types=1);

namespace App\Config;

final class PythonSettings
{
    public function __construct(
        public readonly string $binary,
        public readonly string $scriptPath,
        public readonly int $timeoutSeconds,
    ) {}

    public static function fromEnv(Env $env, string $rootDir): self
    {
        $binary = $env->string('PYTHON_PATH') ?? $env->string('PYTHON_BIN') ?? 'python3';
        return new self($binary, $rootDir . '/python/procedure_real.py', $env->int('PYTHON_TIMEOUT_SECONDS', 120));
    }
}
