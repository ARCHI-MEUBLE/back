<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Domain\Calendly\CalendlyWebhookSignature;
use App\Domain\Cart\CartQuantity;
use App\Domain\Payment\PaymentAmountPolicy;
use App\Infrastructure\Calendly\CalendlyApiClient;
use App\Infrastructure\RateLimit\ClientIp;
use App\Lib\Html;
use App\Lib\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class SecurityPrimitivesTest extends TestCase
{
    public function testClientIpIgnoresSpoofedForwardedHeaders(): void
    {
        self::assertSame('203.0.113.9', ClientIp::from(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']));
        self::assertSame('198.51.100.7', ClientIp::from(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 198.51.100.7, 100.64.0.3']));
        self::assertSame('10.0.0.2', ClientIp::from(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4', 'HTTP_CLIENT_IP' => '5.6.7.8']));
        self::assertSame('0.0.0.0', ClientIp::from([]));
    }

    public function testClientIpTrustsTheFrontOnlyWithTheSharedSecret(): void
    {
        $secret = str_repeat('k', ClientIp::MIN_SECRET_LENGTH);
        $front = [
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '76.76.21.9',
            ClientIp::CLIENT_IP_HEADER => '203.0.113.50',
            ClientIp::PROXY_SECRET_HEADER => $secret,
        ];
        try {
            ClientIp::trustFrontProxy(null);
            self::assertSame('203.0.113.50', ClientIp::from($front, $secret));
            self::assertSame('2001:db8::1', ClientIp::from([...$front, ClientIp::CLIENT_IP_HEADER => '2001:db8::1'], $secret));

            self::assertSame('76.76.21.9', ClientIp::from([...$front, ClientIp::PROXY_SECRET_HEADER => 'devine'], $secret));
            self::assertSame('76.76.21.9', ClientIp::from(array_diff_key($front, [ClientIp::PROXY_SECRET_HEADER => true]), $secret));
            self::assertSame('76.76.21.9', ClientIp::from([...$front, ClientIp::CLIENT_IP_HEADER => 'pas-une-adresse'], $secret));
            self::assertSame('76.76.21.9', ClientIp::from($front));
            self::assertSame('76.76.21.9', ClientIp::from([...$front, ClientIp::PROXY_SECRET_HEADER => 'court'], 'court'));

            ClientIp::trustFrontProxy($secret);
            self::assertSame('203.0.113.50', ClientIp::from($front));
            ClientIp::trustFrontProxy('court');
            self::assertSame('76.76.21.9', ClientIp::from([...$front, ClientIp::PROXY_SECRET_HEADER => 'court']));
        } finally {
            ClientIp::trustFrontProxy(null);
        }
    }

    public function testHtmlEscaping(): void
    {
        self::assertSame('&lt;a href=&quot;x&quot;&gt;&#039;', Html::e('<a href="x">\''));
        self::assertSame(['n' => '&lt;b&gt;', 'price' => 12.5, 'nested' => ['x' => '&amp;']], Html::escapeAll(['n' => '<b>', 'price' => 12.5, 'nested' => ['x' => '&']]));
    }

    public function testCalendlyWebhookSignature(): void
    {
        $signature = new CalendlyWebhookSignature('key');
        $header = $signature->sign('{"a":1}', 1_700_000_000);

        self::assertTrue($signature->verify($header, '{"a":1}', 1_700_000_100));
        self::assertFalse($signature->verify($header, '{"a":2}', 1_700_000_100));
        self::assertFalse($signature->verify($header, '{"a":1}', 1_700_001_000));
        self::assertFalse((new CalendlyWebhookSignature(null))->verify($header, '{"a":1}', 1_700_000_100));
    }

    public function testCalendlyClientOnlyTargetsTheApi(): void
    {
        self::assertTrue(CalendlyApiClient::isApiUrl('https://api.calendly.com/invitees/abc'));
        self::assertFalse(CalendlyApiClient::isApiUrl('https://api.calendly.com.evil.example/x'));
        self::assertFalse(CalendlyApiClient::isApiUrl('https://api.calendly.com@evil.example/x'));
        self::assertFalse(CalendlyApiClient::isApiUrl('http://api.calendly.com/x'));
    }

    public function testAmountsAreRoundedToTheCent(): void
    {
        self::assertSame(85075, PaymentAmountPolicy::cents('850.75'));
        self::assertSame(1999, PaymentAmountPolicy::cents(19.99));
        self::assertSame(120.0, PaymentAmountPolicy::dueAmount(['deposit_amount' => '120.00'], 'deposit'));
    }

    public function testCartQuantities(): void
    {
        self::assertSame(1, CartQuantity::forAdd(null));
        self::assertSame(3, CartQuantity::forAdd('3'));
        self::assertSame(0, CartQuantity::forUpdate(0));
        foreach ([-1, 0, 51, 'abc', 1.5] as $invalid) {
            try {
                CartQuantity::forAdd($invalid);
                self::fail('Quantity accepted: ' . var_export($invalid, true));
            } catch (ValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
