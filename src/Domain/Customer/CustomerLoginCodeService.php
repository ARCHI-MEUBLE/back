<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\Shared\DomainException;
use App\Http\Session;
use App\Infrastructure\Mail\EmailGateway;
use App\Infrastructure\RateLimit\Throttle;

final class CustomerLoginCodeService
{
    private const TOO_MANY = 'Trop de tentatives. Merci de réessayer dans quelques minutes.';

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerVerificationRepository $verifications,
        private readonly EmailGateway $mail,
        private readonly Throttle $throttle,
    ) {}

    public function requestLoginCode(string $email, ?string $firstName, ?string $lastName, string $ip): bool
    {
        $this->throttle->consume($ip, 'customer_login_code_ip', 20, 60, self::TOO_MANY);
        $email = CustomerAuthService::normalize($email);
        $customer = $this->customers->findAuthRowByEmail($email);
        if ($customer === null) {
            $firstName = trim((string) $firstName);
            $lastName = trim((string) $lastName);
            if ($firstName === '' || $lastName === '') {
                return false;
            }
            $this->customers->createPasswordless($email, $firstName, $lastName);
            $name = $firstName;
        } else {
            $name = (string) $customer['first_name'];
        }
        $this->throttle->consume($email, 'verification_code_send', 5, 60, 'Trop de demandes. Veuillez attendre avant de demander un nouveau code.');
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->verifications->issueCode($email, $code, date('Y-m-d H:i:s', strtotime('+15 minutes')));
        if (!$this->mail->sendLoginCode($email, $name, $code)) {
            throw new DomainException("Impossible d'envoyer le code pour le moment. Réessayez dans quelques instants.", 503);
        }
        return true;
    }

    public function verifyLoginCode(string $email, string $code, Session $session): array
    {
        $email = CustomerAuthService::normalize($email);
        $this->throttle->assertAllowed($email, 'email_verify', 5, 15, self::TOO_MANY);
        $customer = $this->customers->findAuthRowByEmail($email);
        $verification = $customer === null ? null : $this->verifications->findValidUnusedCode($email, $code);
        if ($customer === null || $verification === null) {
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
        CustomerAuthService::openSession($full, $session);
        $this->verifications->clearCodesForEmail($email);
        return $full;
    }
}
