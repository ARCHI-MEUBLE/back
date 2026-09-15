<?php

declare(strict_types=1);

namespace Tests\Support;

use JsonException;

final class ApiResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {}

    public function json(): mixed
    {
        try {
            return json_decode($this->body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ['__invalid_json__' => substr($this->body, 0, 200)];
        }
    }

    public function data(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];
        return $values === [] ? null : implode(', ', $values);
    }

    public function cookies(): array
    {
        return $this->headers['set-cookie'] ?? [];
    }

    public function contentType(): string
    {
        return (string) $this->header('content-type');
    }
}
