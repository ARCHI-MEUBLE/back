<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class ContractTestCase extends TestCase
{
    private static ?ServerProcess $server = null;
    private static ?ApiClient $admin = null;
    private static ?ApiClient $customer = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$server !== null) {
            return;
        }
        $root = self::root();
        DbSeeder::reset($root, DatabaseUrl::fromEnv());
        self::resetBackupState($root);
        self::$server = ServerProcess::start($root, self::entry(), self::port(), self::serverEnv($root));
        $server = self::$server;
        register_shutdown_function(static fn() => $server->stop());
    }

    protected function client(): ApiClient
    {
        return new ApiClient(self::baseUrl());
    }

    protected function admin(): ApiClient
    {
        if (self::$admin === null) {
            self::$admin = $this->client();
            $login = self::$admin->post('/backend/api/admin-auth/login.php', [
                'email' => DbSeeder::ADMIN_EMAIL,
                'password' => DbSeeder::ADMIN_PASSWORD,
            ]);
            if ($login->status !== 200) {
                throw new RuntimeException('Admin login failed: ' . $login->body);
            }
        }
        return self::$admin;
    }

    protected function customer(): ApiClient
    {
        if (self::$customer === null) {
            self::$customer = $this->client();
            $login = self::$customer->post('/backend/api/customers/login.php', [
                'email' => DbSeeder::CUSTOMER_EMAIL,
                'password' => DbSeeder::CUSTOMER_PASSWORD,
            ]);
            if ($login->status !== 200) {
                throw new RuntimeException('Customer login failed: ' . $login->body);
            }
        }
        return self::$customer;
    }

    protected function assertSnapshot(string $name, ApiResponse $response): void
    {
        $actual = ['status' => $response->status, 'shape' => Shape::of($response->json())];
        $file = self::root() . '/tests/Contract/__snapshots__/' . $name . '.json';
        $encoded = json_encode($actual, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        if (getenv('UPDATE_SNAPSHOTS') === '1' || (!file_exists($file) && getenv('CI') === false)) {
            file_put_contents($file, $encoded);
        }
        if (!file_exists($file)) {
            self::fail('Missing snapshot ' . $name . ', run with UPDATE_SNAPSHOTS=1');
        }
        $expected = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expected, json_decode($encoded, true, 512, JSON_THROW_ON_ERROR), 'Contract changed for ' . $name . ': ' . $response->body);
    }

    protected function skipOnLegacy(string $reason): void
    {
        if (self::entry() === 'router.php') {
            self::markTestIncomplete('Legacy behaviour differs, target contract: ' . $reason);
        }
    }

    protected function assertJsonResponse(ApiResponse $response): void
    {
        self::assertStringStartsWith('application/json', $response->contentType(), $response->body);
    }

    protected static function baseUrl(): string
    {
        return 'http://127.0.0.1:' . self::port();
    }

    protected static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function resetBackupState(string $root): void
    {
        foreach (['backup-rate-limit.json', 'backup-access.log'] as $file) {
            $path = $root . '/storage/' . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private static function entry(): string
    {
        $entry = getenv('CONTRACT_ENTRY');
        return is_string($entry) && $entry !== '' ? $entry : 'router.php';
    }

    private static function port(): int
    {
        $port = getenv('TEST_SERVER_PORT');
        return is_string($port) && $port !== '' ? (int) $port : 8089;
    }

    private static function serverEnv(string $root): array
    {
        return [
            'DATABASE_URL' => DatabaseUrl::fromEnv(),
            'APP_ENV' => 'local',
            'FRONTEND_URL' => 'http://localhost:3000',
            'PYTHON_PATH' => $root . '/tests/Support/bin/fake-python',
            'PYTHON_BIN' => $root . '/tests/Support/bin/fake-python',
            'OUTPUT_DIR' => $root . '/models',
            'MODELS_DIR' => $root . '/models',
            'ADMIN_EMAIL' => DbSeeder::ADMIN_EMAIL,
            'CALENDLY_PHONE_URL' => 'https://calendly.com/archimeuble/telephone',
            'CALENDLY_VISIO_URL' => 'https://calendly.com/archimeuble/visio',
            'CRISP_WEBSITE_ID' => 'crisp-test',
            'CRON_SECRET' => 'test-cron-secret',
            'BACKUP_API_KEY' => 'test-backup-key',
        ];
    }
}
