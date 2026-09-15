<?php

declare(strict_types=1);

namespace App\Config;

final class Env
{
    private function __construct(private readonly array $values) {}

    public static function load(string $file): self
    {
        $values = [];
        if (is_file($file)) {
            $values = self::parse((string) file_get_contents($file));
        }
        return new self($values);
    }

    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    public function string(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }
        $fromFile = $this->values[$name] ?? null;
        if (is_string($fromFile) && $fromFile !== '') {
            return $fromFile;
        }
        return $default;
    }

    public function require(string $name): string
    {
        $value = $this->string($name);
        if ($value === null) {
            throw new ConfigurationException(sprintf('Missing required environment variable %s', $name));
        }
        return $value;
    }

    public function int(string $name, int $default): int
    {
        $value = $this->string($name);
        return $value === null ? $default : (int) $value;
    }

    public function bool(string $name, bool $default): bool
    {
        $value = $this->string($name);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function list(string $name, array $default = []): array
    {
        $value = $this->string($name);
        if ($value === null) {
            return $default;
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $item): bool => $item !== ''));
    }

    private static function parse(string $content): array
    {
        $values = [];
        $lines = preg_split('/\r?\n/', $content);
        foreach ($lines === false ? [] : $lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (preg_match('/^"(.*)"$/', $value, $matches) === 1 || preg_match("/^'(.*)'$/", $value, $matches) === 1) {
                $value = $matches[1];
            }
            $values[trim($key)] = $value;
        }
        return $values;
    }
}
