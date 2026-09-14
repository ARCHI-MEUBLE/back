<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class Accounts
{
    public static function registerVerifiedCustomer(ApiClient $client, string $email, string $password): ApiClient
    {
        $register = $client->post('/backend/api/customers/register.php', [
            'email' => $email,
            'password' => $password,
            'first_name' => 'Nouveau',
            'last_name' => 'Client',
            'phone' => '0612345678',
            'address' => '2 rue Neuve',
            'city' => 'Lille',
            'postal_code' => '59000',
            'country' => 'France',
        ]);
        if ($register->status !== 200 && $register->status !== 201) {
            throw new RuntimeException('Registration failed: ' . $register->body);
        }
        $pdo = DatabaseUrl::connect(DatabaseUrl::fromEnv());
        $statement = $pdo->prepare('SELECT code FROM email_verifications WHERE email = ? AND used = FALSE ORDER BY created_at DESC LIMIT 1');
        $statement->execute([$email]);
        $code = $statement->fetchColumn();
        if (!is_string($code)) {
            throw new RuntimeException('No verification code stored for ' . $email);
        }
        $verify = $client->post('/backend/api/customers/verify-email.php', ['email' => $email, 'code' => $code]);
        if ($verify->status !== 200) {
            throw new RuntimeException('Verification failed: ' . $verify->body);
        }
        $login = $client->post('/backend/api/customers/login.php', ['email' => $email, 'password' => $password]);
        if ($login->status !== 200) {
            throw new RuntimeException('Login failed: ' . $login->body);
        }
        return $client;
    }

    public static function uniqueEmail(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(3)) . '@test.archimeuble.com';
    }
}
