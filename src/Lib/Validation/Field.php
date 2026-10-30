<?php

declare(strict_types=1);

namespace App\Lib\Validation;

final class Field
{
    public const SKIP = "\0skip";

    private bool $required = false;
    private bool $nullable = false;
    private mixed $default = self::SKIP;
    private ?string $requiredMessage = null;
    private array $rules = [];

    private function __construct(private readonly string $type) {}

    public static function string(): self
    {
        return new self('string');
    }

    public static function int(): self
    {
        return new self('int');
    }

    public static function float(): self
    {
        return new self('float');
    }

    public static function bool(): self
    {
        return new self('bool');
    }

    public static function array(): self
    {
        return new self('array');
    }

    public static function any(): self
    {
        return new self('any');
    }

    public function required(?string $message = null): self
    {
        $this->required = true;
        $this->requiredMessage = $message;
        return $this;
    }

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function rule(callable $check, string $message): self
    {
        $this->rules[] = [$check, $message];
        return $this;
    }

    public function email(?string $message = null): self
    {
        return $this->rule(static fn(mixed $v): bool => is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL) !== false, $message ?? 'Email invalide');
    }

    public function min(float $min, ?string $message = null): self
    {
        return $this->rule(static fn(mixed $v): bool => is_numeric($v) && (float) $v >= $min, $message ?? sprintf('Valeur minimale : %s', $min));
    }

    public function max(float $max, ?string $message = null): self
    {
        return $this->rule(static fn(mixed $v): bool => is_numeric($v) && (float) $v <= $max, $message ?? sprintf('Valeur maximale : %s', $max));
    }

    public function minLength(int $length, ?string $message = null): self
    {
        return $this->rule(static fn(mixed $v): bool => is_string($v) && mb_strlen($v) >= $length, $message ?? sprintf('%d caractères minimum', $length));
    }

    public function maxLength(int $length, ?string $message = null): self
    {
        return $this->rule(static fn(mixed $v): bool => is_string($v) && mb_strlen($v) <= $length, $message ?? sprintf('%d caractères maximum', $length));
    }

    public function oneOf(array $allowed, ?string $message = null): self
    {
        return $this->rule(static fn(mixed $v): bool => in_array($v, $allowed, true), $message ?? 'Valeur non autorisée');
    }

    public function pattern(string $regex, string $message): self
    {
        return $this->rule(static fn(mixed $v): bool => is_string($v) && preg_match($regex, $v) === 1, $message);
    }

    public function onMissing(string $name, bool $presentButEmpty): mixed
    {
        if ($this->required) {
            throw new ValidationException($this->requiredMessage ?? sprintf('Le champ %s est requis', $name));
        }
        if ($this->default !== self::SKIP) {
            return $this->default;
        }
        return $this->nullable || $presentButEmpty ? null : self::SKIP;
    }

    public function coerce(string $name, mixed $value): mixed
    {
        $coerced = Coercion::to($this->type, $value);
        if ($coerced === Coercion::INVALID) {
            throw new ValidationException(sprintf('Le champ %s est invalide', $name));
        }
        foreach ($this->rules as [$check, $message]) {
            if (!$check($coerced)) {
                throw new ValidationException($message);
            }
        }
        return $coerced;
    }
}
