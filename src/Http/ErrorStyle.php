<?php

declare(strict_types=1);

namespace App\Http;

enum ErrorStyle
{
    case Plain;
    case Success;

    public function payload(string $message): array
    {
        return match ($this) {
            self::Plain => ['error' => $message],
            self::Success => ['success' => false, 'error' => $message],
        };
    }
}
