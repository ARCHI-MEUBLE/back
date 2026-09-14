<?php

declare(strict_types=1);

namespace Tests\Contract\Asset;

use Tests\Support\ContractTestCase;

final class UploadsTest extends ContractTestCase
{
    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\x0f\x00\x01\x01\x01\x00\x18\xdd\x8d\xb0\x00\x00\x00\x00IEND\xaeB`\x82";

    public function testQuoteRequest(): void
    {
        $file = $this->pngFile();
        $response = $this->client()->multipart('/backend/api/quote-request/index.php', [
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'email' => 'jean@test.archimeuble.com',
            'phone' => '0600000000',
            'description' => 'Une cuisine',
        ], ['files[]' => ['path' => $file, 'type' => 'image/png', 'name' => 'plan.png']]);
        $this->assertSnapshot('quote-request.create', $response);
        $this->assertSnapshot('quote-request.invalid', $this->client()->multipart('/backend/api/quote-request/index.php', ['first_name' => 'Jean']));
        unlink($file);
    }

    public function testBase64UploadGuards(): void
    {
        $this->assertSnapshot('upload.unauthorized', $this->client()->post('/backend/api/upload.php', ['fileName' => 'a.png', 'fileType' => 'image/png', 'data' => 'AAAA']));
        $this->assertSnapshot('upload.invalid', $this->admin()->post('/backend/api/upload.php', ['fileName' => 'a.png']));
        $this->assertSnapshot('upload.unsupported', $this->admin()->post('/backend/api/upload.php', ['fileName' => 'a.gif', 'fileType' => 'image/gif', 'data' => 'AAAA']));
    }

    public function testBase64Upload(): void
    {
        $response = $this->admin()->post('/backend/api/upload.php', ['fileName' => 'a.png', 'fileType' => 'image/png', 'data' => 'data:image/png;base64,' . base64_encode(self::PNG)]);

        $this->assertSnapshot('upload.ok', $response);
        self::assertStringContainsString('/uploads/models/', $response->data()['imagePath']);
    }

    public function testAdminImageUpload(): void
    {
        $file = $this->pngFile();
        $this->assertSnapshot('upload-image.unauthorized', $this->client()->multipart('/backend/api/admin/upload-image.php', [], ['image' => ['path' => $file, 'type' => 'image/png', 'name' => 'photo.png']]));
        $this->assertSnapshot('upload-image.missing', $this->admin()->multipart('/backend/api/admin/upload-image.php', []));
        $ok = $this->admin()->multipart('/backend/api/admin/upload-image.php', [], ['image' => ['path' => $file, 'type' => 'image/png', 'name' => 'photo.png']]);
        $this->assertSnapshot('upload-image.ok', $ok);
        $url = $ok->data()['url'];
        self::assertMatchesRegularExpression('#^/(backend/)?uploads/#', $url);
        self::assertSame(200, $this->client()->get($url)->status, $url);
        self::assertSame(200, $this->client()->get(preg_replace('#^/backend#', '', $url))->status, $url);
        unlink($file);
    }

    public function testTextureUpload(): void
    {
        $file = $this->pngFile();
        $this->assertSnapshot('upload-texture.missing', $this->admin()->multipart('/backend/api/upload-texture.php', []));
        $ok = $this->admin()->multipart('/backend/api/upload-texture.php', [], ['file' => ['path' => $file, 'type' => 'image/png', 'name' => 'texture.png']]);
        $this->assertSnapshot('upload-texture.ok', $ok);
        $url = $ok->data()['url'];
        self::assertStringStartsWith('/textures/', $url);
        self::assertSame(200, $this->client()->get($url)->status);
        unlink(self::root() . '/assets' . $url);
        unlink($file);
    }

    private function pngFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'png') . '.png';
        file_put_contents($path, self::PNG);
        return $path;
    }
}
