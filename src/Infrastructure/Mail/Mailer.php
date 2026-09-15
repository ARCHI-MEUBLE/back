<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

interface Mailer
{
    public function send(string $to, string $subject, string $html): bool;
}
