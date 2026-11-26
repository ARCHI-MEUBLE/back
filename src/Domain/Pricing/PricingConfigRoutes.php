<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;
use App\Lib\Validation\ValidationException;

final class PricingConfigRoutes
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

    public function __construct(private readonly PricingConfigRepository $config) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('pricing-config/index', [new AdminGuard()], ErrorStyle::Success)
            ->get(null, fn(): Response => Response::json(['success' => true, 'data' => $this->config->all()]))
            ->post(null, fn(Request $r): Response => $this->create($r->json()))
            ->put(null, fn(Request $r): Response => $this->update($r->json()))
            ->delete(null, static fn(): Response => throw new DomainException('Les paramètres de la grille ne se suppriment pas : modifiez leur valeur'));
    }

    private function create(array $data): Response
    {
        $key = static fn(string $label): Field => Field::string()->required("$label est obligatoire")
            ->pattern(self::KEY_PATTERN, "$label est invalide (lettres minuscules, chiffres et _)");
        $input = Schema::object([
            'category' => $key('La catégorie'),
            'item_type' => $key('L\'élément'),
            'param_name' => $key('Le nom du paramètre'),
            'unit' => Field::string()->required('L\'unité est obligatoire')
                ->oneOf(PricingConfigRules::UNITS, 'Unité invalide (' . implode(', ', PricingConfigRules::UNITS) . ')'),
            'description' => Field::string()->nullable()->maxLength(300, 'La description ne doit pas dépasser 300 caractères'),
        ])->validate($data);
        $value = PricingConfigRules::value($input, self::submittedValue($data));
        if ($this->config->findDuplicate($input['category'], $input['item_type'], $input['param_name'])) {
            throw new DomainException('Ce paramètre existe déjà : modifiez sa valeur', 409);
        }
        $id = $this->config->create($input + ['param_value' => $value, 'description' => null]);
        return Response::json(['success' => true, 'message' => 'Paramètre créé', 'id' => (string) $id]);
    }

    private function update(array $data): Response
    {
        $id = Schema::object([
            'id' => Field::int()->required('Identifiant du paramètre manquant')->min(1, 'Identifiant du paramètre invalide'),
        ])->validate($data)['id'];
        $row = $this->config->find($id);
        if ($row === null) {
            throw new DomainException('Paramètre introuvable', 404);
        }
        $this->config->updateValue($id, PricingConfigRules::value($row, self::submittedValue($data)));
        return Response::json(['success' => true, 'message' => 'Paramètre mis à jour']);
    }

    private static function submittedValue(array $data): mixed
    {
        if (!array_key_exists('param_value', $data) || $data['param_value'] === '' || $data['param_value'] === null) {
            throw new ValidationException('La valeur est obligatoire');
        }
        return $data['param_value'];
    }
}
