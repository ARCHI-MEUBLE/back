<?php

declare(strict_types=1);

namespace App\Domain\ContactRequest;

use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Shared\DomainException;
use App\Infrastructure\Mail\EmailGateway;

final class ContactRequestService
{
    public function __construct(
        private readonly ContactRequestRepository $requests,
        private readonly AdminNotificationRepository $notifications,
        private readonly EmailGateway $mail,
    ) {}

    public function submit(array $form): array
    {
        foreach (['name', 'email', 'phone', 'message'] as $field) {
            if (!isset($form[$field]) || $form[$field] === '') {
                throw new DomainException('Tous les champs obligatoires doivent être remplis');
            }
        }
        $name = (string) $form['name'];
        $email = (string) $form['email'];
        $phone = (string) $form['phone'];
        $company = (string) ($form['company'] ?? '');
        $subject = (string) ($form['subject'] ?? '');
        $message = (string) $form['message'];

        $requestId = $this->requests->create($name, $email, $phone, $company, $subject, $message);

        $this->notifications->notifyAllAdmins(
            'new_contact_request',
            sprintf('Nouveau message de contact de %s', $name),
        );
        $this->mail->sendNewContactRequestNotification($name, $email, $phone, $company, $subject, $message);

        return ['contact_request_id' => $requestId];
    }

    public function recent(int $limit): array
    {
        return $this->requests->recent($limit);
    }
}
