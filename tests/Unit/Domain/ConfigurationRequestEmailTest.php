<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Configuration\ShareCode;
use App\Domain\Email\ConfigurationRequestEmail;
use PHPUnit\Framework\TestCase;

final class ConfigurationRequestEmailTest extends TestCase
{
    private const SHARE = [
        'code' => 'Ab3dEf7h',
        'configurator_id' => '1',
        'prompt' => 'M1(1500,400,700)EbF',
        'config_data' => ['dimensions' => ['width' => 1500, 'depth' => 400, 'height' => 700], 'styling' => ['colorLabel' => '<b>Chêne</b>']],
    ];

    public function testVisitorInputIsEscaped(): void
    {
        $html = ConfigurationRequestEmail::html(
            ['name' => '<script>alert(1)</script>', 'email' => 'a@b.fr', 'phone' => '', 'message' => "Ligne 1\n<img src=x>", 'price' => 1180.0],
            self::SHARE,
            'http://localhost:3000/configurator/1?partage=Ab3dEf7h',
        );
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img src=x>', $html);
        self::assertStringNotContainsString('<b>Chêne</b>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString("href='http://localhost:3000/configurator/1?partage=Ab3dEf7h'", $html);
        self::assertStringContainsString('Téléphone non renseigné', $html);
        self::assertStringContainsString('1 180 €', $html);
        self::assertStringContainsString('1500 mm', $html);
    }

    public function testShareCodesAreUnambiguous(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = ShareCode::generate();
            self::assertTrue(ShareCode::isValid($code), $code);
            self::assertDoesNotMatchRegularExpression('/[0O1lI]/', $code);
        }
        self::assertFalse(ShareCode::isValid('abc'));
        self::assertFalse(ShareCode::isValid('abcdefg0'));
    }
}
