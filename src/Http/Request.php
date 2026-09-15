<?php

declare(strict_types=1);

namespace App\Http;

use App\Lib\Json;
use App\Lib\Validation\ValidationException;

final class Request
{
    private string $pathInfo = '';
    private array $params = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly array $cookies,
        public readonly array $form,
        public readonly array $files,
        private readonly string $body,
        public readonly string $host,
    ) {}

    public static function fromGlobals(): self
    {
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (is_string($name) && str_starts_with($name, 'HTTP_') && is_string($value)) {
                $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
            }
        }
        if (is_string($_SERVER['CONTENT_TYPE'] ?? null)) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return new self(
            is_string($_SERVER['REQUEST_METHOD'] ?? null) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET',
            trim(is_string($path) ? $path : '/', '/'),
            $_GET,
            $headers,
            $_COOKIE,
            $_POST,
            $_FILES,
            (string) file_get_contents('php://input'),
            is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : '',
        );
    }

    public function withMatch(string $pathInfo, array $params): self
    {
        $clone = clone $this;
        $clone->pathInfo = $pathInfo;
        $clone->params = $params;
        return $clone;
    }

    public function pathInfo(): string
    {
        return $this->pathInfo;
    }

    public function param(string $name): ?string
    {
        $value = $this->params[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;
        return is_string($value) ? $value : null;
    }

    public function origin(): string
    {
        return $this->header('origin') ?? '';
    }

    public function queryString(string $name): ?string
    {
        $value = $this->query[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    public function queryInt(string $name): ?int
    {
        $value = $this->queryString($name);
        return $value === null || !is_numeric($value) ? null : (int) $value;
    }

    public function rawBody(): string
    {
        return $this->body;
    }

    public function json(): array
    {
        if (trim($this->body) === '') {
            return [];
        }
        $decoded = Json::decode($this->body);
        if (!is_array($decoded)) {
            throw new ValidationException('Corps JSON invalide');
        }
        return $decoded;
    }

    public function jsonOrEmpty(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isPreflight(): bool
    {
        return $this->method === 'OPTIONS';
    }
}
