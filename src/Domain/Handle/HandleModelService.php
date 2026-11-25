<?php

declare(strict_types=1);

namespace App\Domain\Handle;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;

final class HandleModelService
{
    public function __construct(private readonly HandleModelRepository $models) {}

    public function create(array $input): int
    {
        $data = HandleModelSchemas::validate($input);
        $this->ensureNameIsFree($data['name'], null);
        $data['position'] ??= $this->models->nextPosition();
        return $this->models->create($data);
    }

    public function update(array $input): void
    {
        $id = self::id($input);
        $current = $this->models->findById($id) ?? throw new NotFoundException('Modèle de poignée non trouvé');
        $changes = array_intersect_key($input, array_flip(HandleModelSchemas::FIELDS));
        $data = HandleModelSchemas::validate(array_replace($current, $changes));
        $this->ensureNameIsFree($data['name'], $id);
        $data['position'] ??= $current['position'];
        $this->models->update($id, $data);
    }

    public function delete(array $input): void
    {
        if (!$this->models->delete(self::id($input))) {
            throw new NotFoundException('Modèle de poignée non trouvé');
        }
    }

    private function ensureNameIsFree(string $name, ?int $id): void
    {
        if ($this->models->nameTaken($name, $id)) {
            throw new DomainException('Ce nom de poignée existe déjà');
        }
    }

    private static function id(array $input): int
    {
        return (int) HandleModelSchemas::id()->first($input, 'id');
    }
}
