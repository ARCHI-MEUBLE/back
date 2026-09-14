<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\Shared\ConflictException;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Session;
use App\Infrastructure\Mail\EmailGateway;

final class CustomerAuthService
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerVerificationRepository $verifications,
        private readonly EmailGateway $mail,
        private readonly string $frontendUrl,
    ) {}

    public function register(array $data): RegistrationOutcome
    {
        $existing = $this->customers->findAuthRowByEmail($data['email']);
        if ($existing !== null) {
            if (($existing['email_verified'] ?? false) === true) {
                throw new ConflictException('Un compte avec cet email existe déjà');
            }
            $this->issueVerificationCode($data['email'], (string) $existing['first_name']);
            return new RegistrationOutcome($data['email'], isNewAccount: false);
        }
        $this->customers->create($data);
        $this->issueVerificationCode($data['email'], $data['first_name']);
        return new RegistrationOutcome($data['email'], isNewAccount: true);
    }

    public function login(string $email, string $password, Session $session): array
    {
        $customer = $this->customers->verifyCredentials($email, $password);
        if ($customer === null) {
            throw new UnauthorizedException('Email ou mot de passe incorrect');
        }
        $this->openSession($customer, $session);
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
        $customer = $this->customers->findAuthRowByEmail($email);
        if ($customer === null) {
            throw new NotFoundException('Compte non trouvé');
        }
        if (($customer['email_verified'] ?? false) === true) {
            throw new DomainException('Ce compte est déjà vérifié');
        }
        $verification = $this->verifications->findValidUnusedCode($email, $code);
        if ($verification === null) {
            throw new DomainException('Code invalide');
        }
        if (strtotime((string) $verification['expires_at']) < time()) {
            throw new DomainException('Code expiré. Veuillez demander un nouveau code.');
        }
        $this->verifications->markCodeUsed((int) $verification['id']);
        $this->customers->markEmailVerified($email);
        $full = (array) $this->customers->findFullById((int) $customer['id']);
        $this->openSession($full, $session);
        $this->verifications->clearCodesForEmail($email);
        return $full;
    }

    public function resendCode(string $email): void
    {
        $customer = $this->customers->findAuthRowByEmail($email);
        if ($customer === null) {
            return;
        }
        if (($customer['email_verified'] ?? false) === true) {
            throw new DomainException('Ce compte est déjà vérifié. Vous pouvez vous connecter.');
        }
        if ($this->verifications->recentCodeCount($email) >= 3) {
            throw new DomainException('Trop de demandes. Veuillez attendre avant de demander un nouveau code.', 429);
        }
        $this->issueVerificationCode($email, (string) $customer['first_name']);
    }

    public function forgotPassword(string $email): void
    {
        $customer = $this->customers->findAuthRowByEmail($email);
        if ($customer === null) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $this->verifications->issueResetToken($email, $token, date('Y-m-d H:i:s', strtotime('+1 hour')));
        $name = $customer['first_name'] !== '' ? $customer['first_name'] : 'Client';
        $this->mail->sendPasswordReset($email, $name, rtrim($this->frontendUrl, '/') . '/auth/reset-password?token=' . $token);
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $reset = $this->verifications->findValidResetToken($token);
        if ($reset === null) {
            throw new DomainException('Lien de réinitialisation invalide ou expiré');
        }
        $customer = $this->customers->findAuthRowByEmail((string) $reset['email']);
        if ($customer === null) {
            throw new NotFoundException('Client introuvable');
        }
        $this->customers->updatePasswordHash((int) $customer['id'], password_hash($newPassword, PASSWORD_BCRYPT));
        $this->verifications->clearResetTokensForEmail((string) $reset['email']);
    }

    private function issueVerificationCode(string $email, string $firstName): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->verifications->issueCode($email, $code, date('Y-m-d H:i:s', strtotime('+15 minutes')));
        $this->mail->sendVerificationCode($email, $firstName, $code);
    }

    private function openSession(array $customer, Session $session): void
    {
        $session->remove('admin_id');
        $session->remove('admin_email');
        $session->remove('is_admin');
        $session->set('customer_id', $customer['id']);
        $session->set('customer_email', $customer['email']);
        $session->set('customer_name', trim($customer['first_name'] . ' ' . $customer['last_name']));
    }
}
