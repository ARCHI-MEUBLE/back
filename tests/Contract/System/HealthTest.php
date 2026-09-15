<?php

declare(strict_types=1);

namespace Tests\Contract\System;

use Tests\Support\ContractTestCase;

final class HealthTest extends ContractTestCase
{
    public function testRootIsHealthy(): void
    {
        $response = $this->client()->get('/');

        $this->assertJsonResponse($response);
        $this->assertSnapshot('system.health', $response);
    }

    public function testHealthAlias(): void
    {
        $this->assertSnapshot('system.health', $this->client()->get('/health'));
    }

    public function testTestEndpoint(): void
    {
        $response = $this->client()->get('/backend/api/test.php');

        $this->assertSnapshot('system.test', $response);
    }

    public function testUnknownEndpointIsJson404(): void
    {
        $response = $this->client()->get('/api/does-not-exist');

        $this->assertJsonResponse($response);
        $this->assertSnapshot('system.not-found', $response);
    }

    public function testPublicConfig(): void
    {
        $response = $this->client()->get('/backend/api/config.php');

        $this->assertSnapshot('system.config', $response);
        self::assertSame('https://calendly.com/archimeuble/telephone', $response->data()['calendly']['phoneUrl']);
    }
}
