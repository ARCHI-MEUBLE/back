<?php

declare(strict_types=1);

namespace App\Http;

use App\Lib\Json;

final class Response
{
    private array $headers = [];

    private function __construct(
        public readonly int $status,
        private readonly string $body,
        private readonly ?string $file = null,
    ) {}

    public static function json(mixed $payload, int $status = 200): self
    {
        return (new self($status, Json::encode($payload)))->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function empty(int $status = 204): self
    {
        return new self($status, '');
    }

    public static function text(string $body, string $contentType, int $status = 200): self
    {
        return (new self($status, $body))->withHeader('Content-Type', $contentType);
    }

    public static function file(string $path, string $contentType, ?string $downloadName = null): self
    {
        $response = (new self(200, '', $path))
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Length', (string) filesize($path));
        if ($downloadName !== null) {
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $downloadName . '"');
        }
        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone = new self($status, $this->body, $this->file);
        $clone->headers = $this->headers;
        return $clone;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->file !== null) {
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}
