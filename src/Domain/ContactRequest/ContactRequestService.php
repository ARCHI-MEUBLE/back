<?php

declare(strict_types=1);

namespace App\Domain\ContactRequest;

use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\QuoteRequest\LeadForm;
use App\Infrastructure\Mail\EmailGateway;
use App\Infrastructure\RateLimit\Throttle;

final class ContactRequestService
{
    public function __construct(
        private readonly ContactRequestRepository $requests,
        private readonly AdminNotificationRepository $notifications,
        private readonly EmailGateway $mail,
        private readonly Throttle $throttle,
    ) {}

    public function submit(array $form, string $ip): array
    {
        $fields = LeadForm::fields($form, ['name' => 150, 'email' => 200, 'phone' => 30, 'message' => 5000], ['company' => 150, 'subject' => 200]);
        $this->throttle->consume($ip, 'contact_request_ip', 5, 60);
        ['name' => $name, 'email' => $email, 'phone' => $phone, 'company' => $company, 'subject' => $subject, 'message' => $message] = $fields;

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
