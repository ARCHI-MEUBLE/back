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

final class MaterialPriceRoutes
{
    public function __construct(
        private readonly MaterialPriceRepository $materials,
        private readonly PricingConfigRepository $config,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/material-prices', [new AdminGuard()], ErrorStyle::Success)
            ->get(null, fn(): Response => Response::json(['success' => true, 'data' => [
                'materials' => $this->materials->all(),
                'default_price_per_m2' => number_format($this->config->grid()->get('materials', 'default', 'price_per_m2'), 2, '.', ''),
            ]]))
            ->post(null, fn(Request $r): Response => $this->dispatch($r->jsonOrEmpty()));
    }

    private function dispatch(array $input): Response
    {
        $action = $input['action'] ?? null;
        if ($action !== 'set' && $action !== 'delete') {
            throw new DomainException('Action invalide');
        }
        $material = self::material($input);
        if ($action === 'set') {
            $price = Schema::object([
                'price_per_m2' => Field::float()->required('Le prix au m² est obligatoire')
                    ->min(0, 'Le prix au m² doit être compris entre 0 et 100 000 €')
                    ->max(100000, 'Le prix au m² doit être compris entre 0 et 100 000 €'),
            ])->validate($input)['price_per_m2'];
            $this->materials->set($material, (float) $price);
            return Response::json(['success' => true]);
        }
        $colors =$this->materials->colorCount($material);
        if ($colors > 0) {
            throw new DomainException(sprintf('Cette matière est encore utilisée par %d couleur%s : changez leur matière avant de retirer son prix', $colors, $colors > 1 ? 's' : ''));
        }
        if (!$this->materials->delete($material)) {
            throw new DomainException('Aucun prix n\'est enregistré pour cette matière');
        }
        return Response::json(['success' => true]);
    }

    private static function material(array $input): string
    {
        $material = Schema::object([
            'material' => Field::string()->required('Le nom de la matière est obligatoire')
                ->minLength(1, 'Le nom de la matière est obligatoire')
                ->maxLength(120, 'Le nom de la matière ne doit pas dépasser 120 caractères'),
        ])->validate($input)['material'];
        return (string) $material;
    }
}
