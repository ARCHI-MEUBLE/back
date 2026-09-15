<?php

declare(strict_types=1);

namespace Tests\Contract\Http;

use Tests\Support\ContractTestCase;

final class StaticAndCorsTest extends ContractTestCase
{
    public function testTextureIsServed(): void
    {
        $response = $this->client()->get('/textures/chene_brun.png');

        self::assertSame(200, $response->status);
        self::assertSame('image/png', $response->contentType());
    }

    public function testMissingStaticIs404(): void
    {
        self::assertSame(404, $this->client()->get('/textures/missing.png')->status);
        self::assertSame(404, $this->client()->get('/models/missing.glb')->status);
    }

    public function testPreflightAllowedOrigin(): void
    {
        $response = $this->client()->options('/backend/api/categories.php', ['Origin: http://localhost:3000', 'Access-Control-Request-Method: POST']);

        self::assertSame(204, $response->status);
        self::assertSame('http://localhost:3000', $response->header('access-control-allow-origin'));
        self::assertSame('true', $response->header('access-control-allow-credentials'));
        self::assertStringContainsString('DELETE', (string) $response->header('access-control-allow-methods'));
    }

    public function testPreflightFrontendUrlAndVercelPreview(): void
    {
        $vercel = $this->client()->options('/api/categories', ['Origin: https://archimeuble-git-dev.vercel.app']);
        self::assertSame(204, $vercel->status);
        self::assertSame('https://archimeuble-git-dev.vercel.app', $vercel->header('access-control-allow-origin'));
        $prod = $this->client()->options('/api/categories', ['Origin: https://www.archimeuble.com']);
        self::assertSame('https://www.archimeuble.com', $prod->header('access-control-allow-origin'));
    }

    public function testUnknownOriginGetsNoCredentialedAccess(): void
    {
        $response = $this->client()->get('/backend/api/categories.php', ['Origin: https://evil.example']);

        self::assertNotSame('https://evil.example', $response->header('access-control-allow-origin'));
    }

    public function testJsonContentTypeOnApiResponses(): void
    {
        $response = $this->client()->get('/backend/api/categories.php');

        self::assertSame('application/json; charset=utf-8', $response->contentType());
    }
}
