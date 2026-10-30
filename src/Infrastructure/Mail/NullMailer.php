<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

final class NullMailer implements Mailer
{
    public function send(string $to, string $subject, string $html): bool
    {
        error_log("NullMailer: would send '{$subject}' to {$to}");
        return true;
    }
}
