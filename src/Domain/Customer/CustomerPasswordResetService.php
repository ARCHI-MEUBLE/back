<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Infrastructure\Mail\EmailGateway;
use App\Infrastructure\RateLimit\Throttle;

final class CustomerPasswordResetService
{
    private const TOO_MANY = 'Trop de demandes. Merci de réessayer plus tard.';

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerVerificationRepository $verifications,
        private readonly EmailGateway $mail,
        private readonly string $frontendUrl,
        private readonly Throttle $throttle,
    ) {}

    public function forgotPassword(string $email, string $ip): void
    {
        $email = CustomerAuthService::normalize($email);
        $this->throttle->consume($ip, 'password_reset_ip', 10, 60, self::TOO_MANY);
        $this->throttle->consume($email, 'password_reset_email', 3, 60, self::TOO_MANY);
        $customer = $this->customers->findAuthRowByEmail($email);
        if ($customer === null) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $this->verifications->issueResetToken($email, hash('sha256', $token), date('Y-m-d H:i:s', strtotime('+1 hour')));
        $name = $customer['first_name'] !== '' ? (string) $customer['first_name'] : 'Client';
        $this->mail->sendPasswordReset((string) $customer['email'], $name, rtrim($this->frontendUrl, '/') . '/auth/reset-password?token=' . $token);
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $reset = $this->verifications->findValidResetToken(hash('sha256', $token));
        if ($reset === null) {
            throw new DomainException('Lien de réinitialisation invalide ou expiré');
        }
        $customer = $this->customers->findAuthRowByEmail((string) $reset['email']);
        if ($customer === null) {
            throw new NotFoundException('Client introuvable');
        }
        $this->customers->updatePasswordHash((int) $customer['id'], password_hash($newPassword, PASSWORD_BCRYPT));
        $this->customers->markEmailVerifiedById((int) $customer['id']);
        $this->verifications->clearResetTokensForEmail((string) $reset['email']);
    }
}
