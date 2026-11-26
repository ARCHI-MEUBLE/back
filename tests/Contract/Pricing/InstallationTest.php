<?php

declare(strict_types=1);

namespace Tests\Contract\Pricing;

use Tests\Support\ContractTestCase;

final class InstallationTest extends ContractTestCase
{
    private const CONFIG = [
        'dimensions' => ['width' => 1700, 'height' => 600, 'depth' => 450],
        'styling' => ['materialKey' => 'mdf_melamine', 'socle' => 'none'],
        'features' => ['doorType' => 'none', 'installation' => true],
    ];

    public function testTheEstimateAddsTheInstallationTheCustomerChose(): void
    {
        $with = $this->estimate(self::CONFIG);
        $without = $this->estimate(array_replace_recursive(self::CONFIG, ['features' => ['installation' => false]]));

        self::assertGreaterThan(0, $with['installation']);
        self::assertSame($without['installation'], $with['installation']);
        self::assertSame($without['price'] + $with['installation'], $with['price']);
        self::assertContains('installation', array_column($with['lines'], 'key'));
        self::assertNotContains('installation', array_column($without['lines'], 'key'));
        self::assertSame($without['delivery'], $with['delivery']);
    }

    public function testASavedConfigurationKeepsThePriceWithItsInstallation(): void
    {
        $estimate = $this->estimate(self::CONFIG);
        $saved = $this->customer()->post('/backend/api/configurations/save.php', [
            'prompt' => 'M1(1700,600,450)EFb',
            'price' => 1,
            'config_data' => self::CONFIG,
        ]);

        self::assertSame(201, $saved->status, $saved->body);
        self::assertSame((float) $estimate['price'], (float) $saved->data()['configuration']['price']);

        $reloaded = $this->customer()->get('/backend/api/configurations/list.php?id=' . $saved->data()['configuration']['id']);
        self::assertSame(200, $reloaded->status, $reloaded->body);
        $configData = json_decode((string) ($reloaded->data()['configuration']['config_string'] ?? ''), true);
        self::assertTrue($configData['features']['installation'] ?? null, $reloaded->body);
    }

    public function testTheInstallationRateIsInTheAdminGrid(): void
    {
        $keys = array_map(
            static fn(array $row): string => $row['category'] . '.' . $row['item_type'] . '.' . $row['param_name'],
            $this->admin()->get('/backend/api/pricing-config/index.php')->data()['data'],
        );

        self::assertContains('installation.furniture.price_per_m2', $keys);
        self::assertContains('installation.furniture.minimum', $keys);
        self::assertContains('uncertainty.installation.relative', $keys);
    }

    private function estimate(array $config): array
    {
        $response = $this->client()->post('/backend/api/pricing/estimate.php', ['config_data' => $config]);
        self::assertSame(200, $response->status, $response->body);
        return $response->data()['data'];
    }
}
