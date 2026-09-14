<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Session;
use App\Lib\Validation\ValidationException;

final class CustomerProfileService
{
    public function __construct(private readonly CustomerRepository $customers) {}

    public function updateBasic(int $customerId, array $input, Session $session): array
    {
        if ($this->customers->findBasicById($customerId) === null) {
            throw new NotFoundException('Client non trouvé');
        }
        $fields = [];
        foreach (['first_name', 'last_name', 'phone', 'address'] as $column) {
            if (isset($input[$column]) && $input[$column] !== '') {
                $fields[$column] = trim((string) $input[$column]);
            } elseif (array_key_exists($column, $input) && in_array($column, ['phone', 'address'], true)) {
                $fields[$column] = null;
            }
        }
        if (isset($input['email']) && $input['email'] !== '') {
            $email = trim((string) $input['email']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new ValidationException('Email invalide');
            }
            if ($this->customers->emailUsedByAnother($email, $customerId)) {
                throw new DomainException('Cet email est déjà utilisé');
            }
            $fields['email'] = $email;
            $session->set('customer_email', $email);
        }
        if ($fields === []) {
            throw new ValidationException('Aucune donnée à mettre à jour');
        }
        return $this->customers->updateBasic($customerId, $fields);
    }

    public function changePassword(int $customerId, string $currentPassword, string $newPassword, int $wrongPasswordStatus = 401): void
    {
        if (!$this->customers->verifyPassword($customerId, $currentPassword)) {
            throw new DomainException('Mot de passe actuel incorrect', $wrongPasswordStatus);
        }
        $this->customers->updatePasswordHash($customerId, password_hash($newPassword, PASSWORD_BCRYPT));
    }

    public function deleteAccount(int $customerId, string $password, Session $session): void
    {
        if (!$this->customers->verifyPassword($customerId, $password)) {
            throw new UnauthorizedException('Mot de passe incorrect');
        }
        $this->customers->delete($customerId);
        $session->destroy();
    }

    public function deleteWithoutConfirmation(int $customerId, Session $session): void
    {
        $this->customers->delete($customerId);
        $session->destroy();
    }
}
