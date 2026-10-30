<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Python;

use App\Infrastructure\Python\ProcOpenRunner;
use PHPUnit\Framework\TestCase;

final class ProcOpenRunnerTest extends TestCase
{
    private function pathEnv(): array
    {
        return ['PATH' => (string) getenv('PATH')];
    }

    public function testCapturesSuccessExitCodeAndOutput(): void
    {
        $runner = new ProcOpenRunner();

        $result = $runner->run([PHP_BINARY, '-r', 'echo "hello"; exit(0);'], sys_get_temp_dir(), $this->pathEnv(), 5);

        self::assertSame(0, $result->exitCode);
        self::assertSame('hello', $result->output);
        self::assertFalse($result->timedOut);
        self::assertTrue($result->succeeded());
    }

    public function testCapturesNonZeroExitCode(): void
    {
        $runner = new ProcOpenRunner();

        $result = $runner->run([PHP_BINARY, '-r', 'fwrite(STDERR, "boom"); exit(3);'], sys_get_temp_dir(), $this->pathEnv(), 5);

        self::assertSame(3, $result->exitCode);
        self::assertSame('boom', $result->output);
        self::assertFalse($result->succeeded());
    }

    public function testTimesOutLongRunningProcess(): void
    {
        $runner = new ProcOpenRunner();

        $result = $runner->run([PHP_BINARY, '-r', 'sleep(5);'], sys_get_temp_dir(), $this->pathEnv(), 1);

        self::assertTrue($result->timedOut);
        self::assertFalse($result->succeeded());
    }
}
