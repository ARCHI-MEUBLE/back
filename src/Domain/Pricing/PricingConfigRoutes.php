<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class PricingConfigRoutes
{
    private const REQUIRED_FIELDS = ['category', 'item_type', 'param_name', 'param_value', 'unit'];

    public function __construct(private readonly PricingConfigRepository $config) {}

    public function register(RouteCollection $routes): void
    {
        $admin = [new AdminGuard()];
        $script = $routes->script('pricing-config/index');

        $script->get(null, fn(Request $r): Response => $this->list($r));
        $script->post(null, fn(Request $r): Response => $this->create($r), $admin);
        $script->put(null, fn(Request $r): Response => $this->update($r), $admin);
        $script->delete(null, fn(Request $r): Response => $this->delete($r), $admin);
    }

    private function list(Request $r): Response
    {
        $category = self::trimmedOrNull($r->queryString('category'));
        $itemType = self::trimmedOrNull($r->queryString('item_type'));
        $id = $r->queryString('id') !== null ? (int) $r->queryString('id') : null;
        $activeOnly = $r->queryString('active_only') !== 'false';
        $results = $this->config->search($id, $category, $itemType, $activeOnly);
        if ($category !== null && $itemType !== null && $results !== []) {
            $grouped = [];
            foreach ($results as $row) {
                $grouped[$row['param_name']] = ['id' => $row['id'], 'value' => $row['param_value'], 'unit' => $row['unit'], 'description' => $row['description'], 'is_active' => $row['is_active']];
            }
            return Response::json(['success' => true, 'data' => $grouped]);
        }
        if ($id !== null && count($results) === 1) {
            return Response::json(['success' => true, 'data' => $results[0]]);
        }
        return Response::json(['success' => true, 'data' => $results]);
    }

    private function create(Request $r): Response
    {
        $data = $r->json();
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                return self::error("Missing required field: $field");
            }
        }
        if (!is_numeric($data['param_value'])) {
            return self::error('param_value must be a number');
        }
        $category = trim((string) $data['category']);
        $itemType = trim((string) $data['item_type']);
        $paramName = trim((string) $data['param_name']);
        if ($this->config->findDuplicate($category, $itemType, $paramName)) {
            return self::error('This parameter already exists', 409);
        }
        $id = $this->config->create([
            'category' => $category, 'item_type' => $itemType, 'param_name' => $paramName,
            'param_value' => (float) $data['param_value'], 'unit' => trim((string) $data['unit']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'is_active' => (isset($data['is_active']) && $data['is_active'] !== '') ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
        ]);
        return Response::json(['success' => true, 'message' => 'Pricing parameter created successfully', 'id' => (string) $id]);
    }

    private function update(Request $r): Response
    {
        $data = $r->json();
        if (!isset($data['id'])) {
            return self::error('Missing id');
        }
        $id = (int) $data['id'];
        if (!$this->config->exists($id)) {
            return self::error('Parameter not found', 404);
        }
        $columns = [];
        if (isset($data['param_value'])) {
            if (!is_numeric($data['param_value'])) {
                return self::error('param_value must be a number');
            }
            $columns['param_value'] = (float) $data['param_value'];
        }
        foreach (['description', 'unit'] as $field) {
            if (isset($data[$field])) {
                $columns[$field] = trim((string) $data[$field]);
            }
        }
        if (isset($data['is_active'])) {
            $columns['is_active'] = $data['is_active'] !== '' ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true;
        }
        if ($columns === []) {
            return self::error('No fields to update');
        }
        $this->config->update($id, $columns);
        return Response::json(['success' => true, 'message' => 'Parameter updated successfully']);
    }

    private function delete(Request $r): Response
    {
        $id = $r->queryString('id') !== null ? (int) $r->queryString('id') : null;
        if ($id === null) {
            return self::error('Missing id');
        }
        if (!$this->config->deactivate($id)) {
            return self::error('Parameter not found', 404);
        }
        return Response::json(['success' => true, 'message' => 'Parameter deactivated successfully']);
    }

    private static function trimmedOrNull(?string $value): ?string
    {
        return $value === null ? null : trim($value);
    }

    private static function error(string $message, int $status = 400): Response
    {
        return Response::json(['success' => false, 'error' => $message], $status);
    }
}
