<?php

declare(strict_types=1);

namespace App\Domain\Handle;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Lib\Validation\ValidationException;

final class HandleModelImport
{
    private const MAX_ROWS = 500;

    public function __construct(
        private readonly Connection $db,
        private readonly HandleModelRepository $models,
    ) {}

    public function run(mixed $rows, bool $dryRun): array
    {
        if (!is_array($rows) || !array_is_list($rows) || $rows === [] || count($rows) > self::MAX_ROWS) {
            throw new DomainException('Le fichier doit contenir entre 1 et ' . self::MAX_ROWS . ' poignées');
        }
        $plan = [];
        $errors = [];
        $seen = [];
        $nextPosition = $this->models->nextPosition();
        foreach ($rows as $index => $row) {
            $line = is_array($row) && is_int($row['line'] ?? null) ? $row['line'] : $index + 2;
            try {
                $step = $this->plan(is_array($row) ? $row : [], $line, $seen);
                if ($step['id'] === null && $step['data']['position'] === null) {
                    $step['data']['position'] = $nextPosition++;
                }
                $plan[] = $step;
            } catch (ValidationException|DomainException $e) {
                $errors[] = ['line' => $line, 'message' => $e->getMessage()];
            }
        }
        $result = ['changes' => array_map(static fn(array $step): array => ['line' => $step['line'], 'name' => $step['data']['name'], 'action' => $step['action']], $plan)];
        foreach (['create', 'update', 'unchanged'] as $action) {
            $result[$action === 'create' ? 'created' : ($action === 'update' ? 'updated' : 'unchanged')] = count(array_filter($plan, static fn(array $step): bool => $step['action'] === $action));
        }
        if ($errors !== [] || $dryRun) {
            return $result + ['errors' => $errors, 'saved' => false];
        }
        $this->db->transaction(function () use ($plan): void {
            foreach ($plan as $step) {
                if ($step['action'] === 'create') {
                    $this->models->create($step['data']);
                } elseif ($step['action'] === 'update') {
                    $this->models->update($step['id'], $step['data']);
                }
            }
        });
        return $result + ['errors' => [], 'saved' => true];
    }

    private function plan(array $row, int $line, array &$seen): array
    {
        $changes = array_intersect_key($row, array_flip(HandleModelSchemas::FIELDS));
        $reference = is_string($changes['reference'] ?? null) && $changes['reference'] !== '' ? $changes['reference'] : null;
        $current = $this->models->findByReferenceOrName($reference, is_string($changes['name'] ?? null) ? $changes['name'] : '');
        $data = HandleModelSchemas::validate($current === null ? $changes : array_replace($current, $changes));
        $id = $current['id'] ?? null;
        $data['position'] ??= $current['position'] ?? null;

        foreach (['référence ' => $data['reference'], 'nom ' => mb_strtolower($data['name'])] as $kind => $key) {
            if ($key === null) {
                continue;
            }
            if (isset($seen[$kind . $key])) {
                throw new DomainException('Même ' . trim($kind) . ' qu\'à la ligne ' . $seen[$kind . $key]);
            }
            $seen[$kind . $key] = $line;
        }
        if ($this->models->nameTaken($data['name'], $id)) {
            throw new DomainException('Le nom « ' . $data['name'] . ' » est déjà celui d\'une autre poignée');
        }
        if ($data['reference'] !== null && $this->models->referenceTaken($data['reference'], $id)) {
            throw new DomainException('La référence ' . $data['reference'] . ' est déjà celle d\'une autre poignée');
        }
        $action = $current === null ? 'create' : (self::differs($current, $data) ? 'update' : 'unchanged');
        return ['id' => $id, 'line' => $line, 'data' => $data, 'action' => $action];
    }

    private static function differs(array $current, array $data): bool
    {
        foreach (HandleModelSchemas::FIELDS as $field) {
            $before = $current[$field] ?? null;
            $after = $data[$field] ?? null;
            $same = is_numeric($before) && is_numeric($after) ? (float) $before === (float) $after : $before === $after;
            if (!$same) {
                return true;
            }
        }
        return false;
    }
}
