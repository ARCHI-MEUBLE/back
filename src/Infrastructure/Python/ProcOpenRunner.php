<?php

declare(strict_types=1);

namespace App\Infrastructure\Python;

use RuntimeException;

final class ProcOpenRunner
{
    public function run(array $argv, string $cwd, array $env, int $timeoutSeconds): ProcessResult
    {
        $argv = array_values($argv);
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($argv, $descriptors, $pipes, $cwd, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start process: ' . $argv[0]);
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $started = microtime(true);
        $output = '';
        $timedOut = false;
        $exitCode = -1;
        while (true) {
            $status = proc_get_status($process);
            $output .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            if (microtime(true) - $started > $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
            usleep(50_000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return new ProcessResult($exitCode, $output, microtime(true) - $started, $timedOut);
    }
}
