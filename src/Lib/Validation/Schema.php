<?php

declare(strict_types=1);

namespace App\Lib\Validation;

final class Schema
{
    private function __construct(private readonly array $fields) {}

    public static function object(array $fields): self
    {
        return new self($fields);
    }

    public function validate(array $input): array
    {
        $output = [];
        foreach ($this->fields as $name => $field) {
            if (!is_string($name) || !$field instanceof Field) {
                continue;
            }
            $present = array_key_exists($name, $input);
            $value = $present ? $input[$name] : null;
            if (!$present || $value === null || $value === '') {
                $missing = $field->onMissing($name, $present && $value !== null);
                if ($missing !== Field::SKIP) {
                    $output[$name] = $missing;
                }
                continue;
            }
            $output[$name] = $field->coerce($name, $value);
        }
        return $output;
    }

    public function first(array $input, string $name): mixed
    {
        return $this->validate($input)[$name] ?? null;
    }
}
