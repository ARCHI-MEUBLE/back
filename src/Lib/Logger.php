<?php

declare(strict_types=1);

namespace App\Lib;

final class Logger
{
    public function __construct(private mixed $stream) {}

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->write('warn', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if (!is_resource($this->stream)) {
            return;
        }
        $line = json_encode(
            ['time' => date('c'), 'level' => $level, 'msg' => $message] + $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
        fwrite($this->stream, ($line === false ? '{"level":"error","msg":"log encoding failed"}' : $line) . PHP_EOL);
    }
}
