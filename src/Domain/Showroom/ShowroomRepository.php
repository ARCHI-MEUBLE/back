<?php

declare(strict_types=1);

namespace App\Domain\Showroom;

final class ShowroomRepository
{
    public function __construct(private readonly string $dataFile) {}

    public function all(): array
    {
        if (!is_file($this->dataFile)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->dataFile), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function findById(string $id): ?array
    {
        foreach ($this->all() as $showroom) {
            if (is_array($showroom) && ($showroom['id'] ?? null) === $id) {
                return $showroom;
            }
        }
        return null;
    }
}
