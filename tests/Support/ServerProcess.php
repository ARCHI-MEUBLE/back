<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class ServerProcess
{
    private function __construct(
        private mixed $process,
        public readonly string $baseUrl,
        public readonly string $logFile,
    ) {}

    public static function start(string $root, string $entry, int $port, array $env): self
    {
        $logFile = $root . '/storage/logs/contract-server.log';
        $sessions = $root . '/storage/sessions';
        $command = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $port,
            '-d',
            'session.save_path=' . $sessions,
            '-t',
            $root,
            $root . '/' . $entry,
        ];
        $descriptors = [0 => ['pipe', 'r'], 1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']];
        $process = proc_open($command, $descriptors, $pipes, $root, array_merge(self::inheritedEnv(), $env));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start php -S');
        }
        fclose($pipes[0]);
        $server = new self($process, 'http://127.0.0.1:' . $port, $logFile);
        $server->waitUntilReady($port);
        return $server;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
    }

    private function waitUntilReady(int $port): void
    {
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            $status = proc_get_status($this->process);
            if (!$status['running']) {
                throw new RuntimeException('php -S exited early, see ' . $this->logFile);
            }
            usleep(100_000);
        }
        throw new RuntimeException('php -S did not become ready on port ' . $port);
    }

    private static function inheritedEnv(): array
    {
        $env = [];
        foreach (['PATH', 'HOME', 'TMPDIR', 'LANG', 'LC_ALL', 'TZ'] as $name) {
            $value = getenv($name);
            if (is_string($value)) {
                $env[$name] = $value;
            }
        }
        return $env;
    }
}
