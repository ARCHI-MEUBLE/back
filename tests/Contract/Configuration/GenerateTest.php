<?php

declare(strict_types=1);

namespace Tests\Contract\Configuration;

use Tests\Support\ContractTestCase;

final class GenerateTest extends ContractTestCase
{
    public function testGenerateProducesModelFiles(): void
    {
        $response = $this->client()->post('/backend/api/generate.php', [
            'prompt' => 'M1(1700,500,730)EFbV3(,T,)',
            'closed' => true,
            'colors' => ['structure' => '#ffffff', 'doors' => '#000000'],
            'deletedPanels' => ['panel-1'],
            'zones' => ['root' => ['type' => 'H']],
        ]);

        $this->assertSnapshot('generate.ok', $response);
        $data = $response->data();
        self::assertStringStartsWith('/models/', $data['glb_url']);
        self::assertStringEndsWith('.dxf', (string) $data['dxf_url']);
        $glb = $this->client()->get($data['glb_url']);
        self::assertSame(200, $glb->status);
        self::assertSame('model/gltf-binary', $glb->contentType());
        self::assertSame(200, $this->client()->get($data['dxf_url'])->status);
    }

    public function testGenerateViaApiForm(): void
    {
        $this->assertSnapshot('generate.ok', $this->client()->post('/api/generate', ['prompt' => 'M1(1200,350,650)EFbV4(,,T,)', 'closed' => false]));
    }

    public function testValidation(): void
    {
        $this->assertSnapshot('generate.missing', $this->client()->post('/backend/api/generate.php', []));
        $this->assertSnapshot('generate.forbidden-chars', $this->client()->post('/backend/api/generate.php', ['prompt' => 'M1(1,1,1); rm -rf /']));
        $this->assertSnapshot('generate.too-long', $this->client()->post('/backend/api/generate.php', ['prompt' => 'M1(' . str_repeat('1', 250) . ')']));
        $this->assertSnapshot('generate.method', $this->client()->get('/backend/api/generate.php'));
    }
}
