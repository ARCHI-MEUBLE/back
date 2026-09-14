<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Config\PathSettings;
use App\Http\Request;
use App\Http\StaticFileHandler;
use PHPUnit\Framework\TestCase;

final class StaticFileHandlerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/static-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/models', 0o777, true);
        mkdir($this->dir . '/textures', 0o777, true);
        file_put_contents($this->dir . '/models/a.glb', 'glb');
        file_put_contents($this->dir . '/textures/t.png', 'png');
        file_put_contents($this->dir . '/secret.png', 'nope');
    }

    protected function tearDown(): void
    {
        foreach (['/models/a.glb', '/textures/t.png', '/secret.png'] as $file) {
            unlink($this->dir . $file);
        }
        rmdir($this->dir . '/models');
        rmdir($this->dir . '/textures');
        rmdir($this->dir);
    }

    public function testServesKnownPrefixes(): void
    {
        $handler = $this->handler();

        $glb = $handler->handle($this->request('models/a.glb'));
        self::assertNotNull($glb);
        self::assertSame(200, $glb->status);
        self::assertSame('model/gltf-binary', $glb->header('Content-Type'));
        self::assertSame('*', $glb->header('Access-Control-Allow-Origin'));
        self::assertSame(200, $handler->handle($this->request('textures/t.png'))?->status);
        self::assertSame(200, $handler->handle($this->request('back/textures/t.png'))?->status);
    }

    public function testIgnoresNonStaticPaths(): void
    {
        self::assertNull($this->handler()->handle($this->request('api/models')));
        self::assertNull($this->handler()->handle($this->request('backend/api/files/dxf.php')));
    }

    public function testMissingOrTraversingFilesAre404(): void
    {
        $handler = $this->handler();

        self::assertSame(404, $handler->handle($this->request('models/missing.glb'))?->status);
        self::assertSame(404, $handler->handle($this->request('models/../secret.png'))?->status);
        self::assertSame(404, $handler->handle($this->request('secret.png'))?->status);
    }

    private function handler(): StaticFileHandler
    {
        return new StaticFileHandler(new PathSettings($this->dir, $this->dir . '/models', $this->dir . '/uploads', $this->dir . '/textures', $this->dir . '/email', $this->dir . '/legacy'));
    }

    private function request(string $path): Request
    {
        return new Request('GET', $path, [], [], [], [], [], '', '127.0.0.1');
    }
}
