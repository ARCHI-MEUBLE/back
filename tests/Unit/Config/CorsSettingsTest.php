<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\CorsSettings;
use App\Config\Env;
use PHPUnit\Framework\TestCase;

final class CorsSettingsTest extends TestCase
{
    public function testAllowsKnownOriginsAndSuffixes(): void
    {
        $cors = CorsSettings::fromEnv(Env::fromArray(['CORS_ALLOWED_ORIGINS' => 'https://extra.example']), 'https://front.example');

        self::assertTrue($cors->allows('http://localhost:3000'));
        self::assertTrue($cors->allows('https://front.example'));
        self::assertTrue($cors->allows('https://extra.example'));
        self::assertTrue($cors->allows('https://archimeuble-git-dev.vercel.app'));
        self::assertTrue($cors->allows('https://www.archimeuble.com'));
    }

    public function testRejectsUnknownOrigins(): void
    {
        $cors = CorsSettings::fromEnv(Env::fromArray([]), 'https://front.example');

        self::assertFalse($cors->allows(''));
        self::assertFalse($cors->allows('https://evil.example'));
        self::assertFalse($cors->allows('http://archimeuble.com'));
        self::assertFalse($cors->allows('https://evil.vercel.app.attacker.example'));
    }
}
