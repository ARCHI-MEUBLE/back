<?php

declare(strict_types=1);

namespace App\Domain\Category;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;

final class CategoryService
{
    public function __construct(private readonly CategoryRepository $categories) {}

    public function list(bool $onlyActive): array
    {
        return $this->categories->all($onlyActive);
    }

    public function find(int $id): array
    {
        $category = $this->categories->findById($id);
        if ($category === null) {
            throw new NotFoundException('Catégorie non trouvée');
        }
        return $category;
    }

    public function create(string $name, ?string $slug, ?string $description, ?string $imageUrl, int $displayOrder, bool $isActive): array
    {
        $id = $this->categories->create($name, $slug ?? CategorySlug::fromName($name), $description, $imageUrl, $displayOrder, $isActive);
        if ($id === null) {
            throw new DomainException('Erreur lors de la création de la catégorie', 500);
        }
        return (array) $this->categories->findById($id);
    }

    public function update(int $id, array $data): array
    {
        if ($data === []) {
            throw new DomainException('Aucune donnée à mettre à jour');
        }
        if (!$this->categories->update($id, $data)) {
            throw new DomainException('Erreur lors de la mise à jour de la catégorie', 500);
        }
        return (array) $this->categories->findById($id);
    }

    public function reorder(array $ids): void
    {
        $this->categories->reorder($ids);
    }

    public function delete(int $id): void
    {
        if (!$this->categories->delete($id)) {
            throw new DomainException('Erreur lors de la suppression de la catégorie', 500);
        }
    }
}
