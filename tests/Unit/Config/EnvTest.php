<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigurationException;
use App\Config\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testParsesFileAndProcessEnvWins(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# comment\nFOO=bar\nQUOTED=\"a b\"\nNUM=12\nFLAG=true\nLIST=a, b,,c\nARCHI_TEST_OVERRIDE=file\n");
        putenv('ARCHI_TEST_OVERRIDE=process');
        $env = Env::load($file);

        self::assertSame('bar', $env->string('FOO'));
        self::assertSame('a b', $env->string('QUOTED'));
        self::assertSame(12, $env->int('NUM', 0));
        self::assertTrue($env->bool('FLAG', false));
        self::assertSame(['a', 'b', 'c'], $env->list('LIST'));
        self::assertSame('process', $env->string('ARCHI_TEST_OVERRIDE'));
        self::assertSame('dflt', $env->string('MISSING', 'dflt'));
        putenv('ARCHI_TEST_OVERRIDE');
        unlink($file);
    }

    public function testRequireFailsFast(): void
    {
        $this->expectException(ConfigurationException::class);

        Env::fromArray([])->require('DATABASE_URL');
    }
}
