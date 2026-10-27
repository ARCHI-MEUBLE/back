<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\Shared\ConflictException;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Session;
use App\Infrastructure\Mail\EmailGateway;
use App\Infrastructure\RateLimit\Throttle;

final class CustomerAuthService
{
    private const TOO_MANY = 'Trop de tentatives. Merci de réessayer dans quelques minutes.';

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerVerificationRepository $verifications,
        private readonly EmailGateway $mail,
        private readonly Throttle $throttle,
    ) {}

    public function register(array $data, string $ip): RegistrationOutcome
    {
        $this->throttle->consume($ip, 'customer_register_ip', 20, 60, self::TOO_MANY);
        $data['email'] = self::normalize((string) $data['email']);
        $existing = $this->customers->findAuthRowByEmail($data['email']);
        if ($existing !== null) {
            if (($existing['email_verified'] ?? false) === true) {
                throw new ConflictException('Un compte avec cet email existe déjà');
            }
            $this->customers->replaceUnverified((int) $existing['id'], $data);
            $this->issueVerificationCode($data['email'], $data['first_name']);
            return new RegistrationOutcome($data['email'], isNewAccount: false);
        }
        $this->customers->create($data);
        $this->issueVerificationCode($data['email'], $data['first_name']);
        return new RegistrationOutcome($data['email'], isNewAccount: true);
    }

    public function login(string $email, string $password, string $ip, Session $session): array
    {
        $key = self::normalize($email);
        $this->throttle->assertAllowed($key, 'customer_login_account', 8, 15, self::TOO_MANY);
        $this->throttle->assertAllowed($ip, 'customer_login_ip', 30, 15, self::TOO_MANY);
        $customer = $this->customers->verifyCredentials($key, $password);
        if ($customer === null) {
            $this->throttle->fail($key, 'customer_login_account', 8, 15);
            $this->throttle->fail($ip, 'customer_login_ip', 30, 15);
            throw new UnauthorizedException('Email ou mot de passe incorrect');
        }
        $this->throttle->reset($key, 'customer_login_account');
        if (!$this->customers->isEmailVerified((int) $customer['id'])) {
            $this->issueVerificationCodeQuietly((string) $customer['email'], (string) $customer['first_name']);
            throw new EmailNotVerifiedException((string) $customer['email']);
        }
        self::openSession($customer, $session);
        return $customer;
    }

    public function current(Session $session): ?array
    {
        if ($session->isAdmin() || !$session->has('customer_id')) {
            return null;
        }
        $customer = $this->customers->findFullById((int) $session->customerId());
        if ($customer === null) {
            $session->destroy();
        }
        return $customer;
    }

    public function verifyEmail(string $email, string $code, Session $session): array
    {
        $email = self::normalize($email);
        $this->throttle->assertAllowed($email, 'email_verify', 5, 15, self::TOO_MANY);
        $customer = $this->customers->findAuthRowByEmail($email);
        if ($customer === null) {
            throw new NotFoundException('Compte non trouvé');
        }
        if (($customer['email_verified'] ?? false) === true) {
            throw new DomainException('Ce compte est déjà vérifié');
        }
        $verification = $this->verifications->findValidUnusedCode($email, $code);
        if ($verification === null) {
            $this->throttle->fail($email, 'email_verify', 5, 15);
            throw new DomainException('Code invalide');
        }
        if (strtotime((string) $verification['expires_at']) < time()) {
            throw new DomainException('Code expiré. Veuillez demander un nouveau code.');
        }
        $this->verifications->markCodeUsed((int) $verification['id']);
        $this->customers->markEmailVerifiedById((int) $customer['id']);
        $this->throttle->reset($email, 'email_verify');
        $full = (array) $this->customers->findFullById((int) $customer['id']);
        self::openSession($full, $session);
        $this->verifications->clearCodesForEmail($email);
        return $full;
    }

    public function resendCode(string $email): void
    {
        $email = self::normalize($email);
        $customer = $this->customers->findAuthRowByEmail($email);
        if ($customer === null) {
            return;
        }
        if (($customer['email_verified'] ?? false) === true) {
            throw new DomainException('Ce compte est déjà vérifié. Vous pouvez vous connecter.');
        }
        $this->issueVerificationCode($email, (string) $customer['first_name']);
    }

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    private function issueVerificationCode(string $email, string $firstName): void
    {
        $this->throttle->consume($email, 'verification_code_send', 5, 60, 'Trop de demandes. Veuillez attendre avant de demander un nouveau code.');
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->verifications->issueCode($email, $code, date('Y-m-d H:i:s', strtotime('+15 minutes')));
        $this->mail->sendVerificationCode($email, $firstName, $code);
    }

    private function issueVerificationCodeQuietly(string $email, string $firstName): void
    {
        try {
            $this->issueVerificationCode(self::normalize($email), $firstName);
        } catch (DomainException) {
        }
    }

    public static function openSession(array $customer, Session $session): void
    {
        $session->regenerate();
        $session->remove('admin_id');
        $session->remove('admin_email');
        $session->remove('is_admin');
        $session->set('customer_id', $customer['id']);
        $session->set('customer_email', $customer['email']);
        $session->set('customer_name', trim($customer['first_name'] . ' ' . $customer['last_name']));
    }
}
