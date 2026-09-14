<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use RuntimeException;

final class DbSeeder
{
    public const ADMIN_EMAIL = 'admin-test@archimeuble.com';
    public const ADMIN_PASSWORD = 'AdminTest#2026';
    public const CUSTOMER_EMAIL = 'client@test.archimeuble.com';
    public const CUSTOMER_PASSWORD = 'ClientTest#2026';
    public const USER_EMAIL = 'user@test.archimeuble.com';
    public const USER_PASSWORD = 'UserTest#2026';

    public static function reset(string $root, string $databaseUrl): void
    {
        $pdo = DatabaseUrl::connect($databaseUrl);
        $pdo->exec('DROP SCHEMA public CASCADE');
        $pdo->exec('CREATE SCHEMA public');
        foreach (self::schemaFiles($root) as $file) {
            $pdo->exec(self::read($file));
        }
        $pdo->exec('ALTER TABLE cart_items DROP CONSTRAINT IF EXISTS cart_items_configuration_id_fkey');
        $pdo->exec('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_configuration_id_fkey');
        self::seedAccounts($pdo);
        $pdo->exec(self::read($root . '/tests/Fixtures/seed.sql'));
    }

    private static function schemaFiles(string $root): array
    {
        $schema = getenv('CONTRACT_SCHEMA');
        if (is_string($schema) && $schema !== '') {
            return array_map(static fn(string $file): string => $root . '/' . trim($file), explode(',', $schema));
        }
        return [$root . '/backend/config/email_templates.sql', $root . '/init_db.sql'];
    }

    private static function seedAccounts(PDO $pdo): void
    {
        $insertCustomer = $pdo->prepare(
            'INSERT INTO customers (id, email, password_hash, first_name, last_name, phone, address, city, postal_code, country, email_verified)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)',
        );
        $insertCustomer->execute([
            self::CUSTOMER_EMAIL,
            password_hash(self::CUSTOMER_PASSWORD, PASSWORD_BCRYPT),
            'Claire',
            'Test',
            '0601020304',
            '1 rue des Tests',
            'Lille',
            '59000',
            'France',
        ]);
        $pdo->exec("SELECT setval('customers_id_seq', 1)");
        $insertAdmin = $pdo->prepare('INSERT INTO admins (username, password, email) VALUES (?, ?, ?)');
        $insertAdmin->execute(['admin-test', password_hash(self::ADMIN_PASSWORD, PASSWORD_BCRYPT), self::ADMIN_EMAIL]);
        $insertUser = $pdo->prepare('INSERT INTO users (id, email, password_hash, name) VALUES (?, ?, ?, ?)');
        $insertUser->execute(['u-1', self::USER_EMAIL, password_hash(self::USER_PASSWORD, PASSWORD_BCRYPT), 'Legacy User']);
    }

    private static function read(string $file): string
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Cannot read ' . $file);
        }
        return $sql;
    }
}
