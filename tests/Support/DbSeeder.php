<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Db\Connection;
use App\Db\Migrator;
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
        (new Migrator(new Connection($pdo), $root . '/src/Db/migrations'))->migrate();
        self::seedAccounts($pdo);
        $pdo->exec(self::read($root . '/tests/Fixtures/seed.sql'));
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
        $insertAdmin = $pdo->prepare('INSERT INTO admins (username, password, password_hash, email) VALUES (?, ?, ?, ?)');
        $hash = password_hash(self::ADMIN_PASSWORD, PASSWORD_BCRYPT);
        $insertAdmin->execute(['admin-test', $hash, $hash, self::ADMIN_EMAIL]);
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
