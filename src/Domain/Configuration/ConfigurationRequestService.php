<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\QuoteRequest\LeadForm;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Infrastructure\Mail\EmailGateway;
use App\Infrastructure\RateLimit\Throttle;

final class ConfigurationRequestService
{
    public function __construct(
        private readonly ConfigurationShareRepository $shares,
        private readonly EmailGateway $mail,
        private readonly Throttle $throttle,
    ) {}

    public function submit(array $form, string $ip): void
    {
        $fields = LeadForm::fields($form, ['name' => 150, 'share_code' => 20], ['phone' => 30, 'message' => 5000]);
        $email = is_scalar($form['email'] ?? null) ? trim((string) $form['email']) : '';
        if ($email !== '' && (mb_strlen($email) > 200 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new DomainException('Adresse email invalide');
        }
        if ($fields['phone'] !== '' && preg_match('/^[0-9+().\s-]{6,30}$/', $fields['phone']) !== 1) {
            throw new DomainException('Numéro de téléphone invalide');
        }
        if ($email === '' && $fields['phone'] === '') {
            throw new DomainException('Indiquez un téléphone ou un e-mail pour que nous puissions vous recontacter');
        }
        $price = is_numeric($form['price'] ?? null) && (float) $form['price'] >= 0 && (float) $form['price'] < 1000000 ? (float) $form['price'] : null;
        $this->throttle->consume($ip, 'configuration_request_ip', 10, 60);
        $share = ShareCode::isValid($fields['share_code']) ? $this->shares->open($fields['share_code']) : null;
        if ($share === null) {
            throw new NotFoundException(ConfigurationShareService::NOT_FOUND);
        }
        $request = ['name' => $fields['name'], 'email' => $email, 'phone' => $fields['phone'], 'message' => $fields['message'], 'price' => $price];
        if (!$this->mail->sendConfigurationRequest($request, $share)) {
            throw new DomainException("Votre demande n'a pas pu être envoyée. Réessayez dans un instant ou appelez-nous.", 502);
        }
    }
}
