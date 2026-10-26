<?php

declare(strict_types=1);

namespace Tests\Contract\Configuration;

use Tests\Support\ContractTestCase;

final class ConfigurationShareTest extends ContractTestCase
{
    private const SHARE_URL = '/backend/api/configurations/share.php';
    private const REQUEST_URL = '/backend/api/configurations/request.php';
    private const SHARE = [
        'configurator_id' => '1',
        'prompt' => 'M1(1500,400,700)EbFH2(T,)',
        'config_data' => [
            'dimensions' => ['width' => 1500, 'depth' => 400, 'height' => 700],
            'styling' => ['finish' => 'Mat', 'colorLabel' => 'Chêne naturel'],
            'features' => ['doorType' => 'none'],
            'advancedZones' => ['id' => 'root', 'type' => 'leaf', 'content' => 'drawer'],
        ],
    ];

    public function testShareAndOpen(): void
    {
        $created = $this->client()->post(self::SHARE_URL, self::SHARE);
        $this->assertSnapshot('configurations.share.create', $created);
        $code = (string) $created->data()['code'];
        self::assertMatchesRegularExpression('/^[a-km-np-zA-HJ-NP-Z2-9]{8}$/', $code);
        self::assertSame($code, $this->client()->post(self::SHARE_URL, self::SHARE)->data()['code']);

        $opened = $this->client()->get(self::SHARE_URL . '?code=' . $code);
        $this->assertSnapshot('configurations.share.open', $opened);
        self::assertSame('1', $opened->data()['share']['configurator_id']);
        self::assertSame(self::SHARE['prompt'], $opened->data()['share']['prompt']);
        self::assertEquals(self::SHARE['config_data'], $opened->data()['share']['config_data']);

        $this->assertSnapshot('configurations.share.open.missing', $this->client()->get(self::SHARE_URL . '?code=abcdefgh'));
        $this->assertSnapshot('configurations.share.open.invalid', $this->client()->get(self::SHARE_URL . '?code=..%2Fx'));
        $this->assertSnapshot('configurations.share.create.invalid', $this->client()->post(self::SHARE_URL, ['config_data' => []] + self::SHARE));
        self::assertSame(400, $this->client()->post(self::SHARE_URL, ['configurator_id' => '../1'] + self::SHARE)->status);
    }

    public function testRequestSentByEmail(): void
    {
        $code = (string) $this->client()->post(self::SHARE_URL, self::SHARE)->data()['code'];
        $request = ['name' => 'Claire', 'phone' => '06 01 02 03 04', 'email' => '', 'message' => 'Pour mon salon', 'share_code' => $code, 'price' => 1180];
        $this->assertSnapshot('configurations.request', $this->client()->post(self::REQUEST_URL, $request));
        $this->assertSnapshot('configurations.request.no-contact', $this->client()->post(self::REQUEST_URL, ['phone' => ''] + $request));
        $this->assertSnapshot('configurations.request.invalid-email', $this->client()->post(self::REQUEST_URL, ['email' => 'pas-un-mail'] + $request));
        self::assertSame(400, $this->client()->post(self::REQUEST_URL, ['phone' => 'appelez-moi'] + $request)->status);
        self::assertSame(400, $this->client()->post(self::REQUEST_URL, ['name' => ''] + $request)->status);
        $this->assertSnapshot('configurations.request.missing-share', $this->client()->post(self::REQUEST_URL, ['share_code' => 'abcdefgh'] + $request));
    }
}
