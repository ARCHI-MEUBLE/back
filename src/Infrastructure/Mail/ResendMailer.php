<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

final class ResendMailer implements Mailer
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $from = 'contact@archimeuble.com',
    ) {}

    public function send(string $to, string $subject, string $html): bool
    {
        $data = ['from' => "ArchiMeuble <{$this->from}>", 'to' => [$to], 'subject' => $subject, 'html' => $html];
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json']);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("EmailService: Email sent successfully via Resend API to {$to}");
            return true;
        }
        error_log("EmailService ERROR: Resend API failed with code {$httpCode}. Response: {$response}");
        return false;
    }
}
