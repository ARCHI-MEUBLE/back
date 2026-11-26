<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class FacadeSettingRoutes
{
    private const LIMITS = [
        'material_price_per_m2' => [0.01, 100000.0, 'Le prix au m² doit être compris entre 0,01 et 100 000 €'],
        'hinge_base_price' => [0.0, 10000.0, "Le prix d'une charnière doit être compris entre 0 et 10 000 €"],
        'hinge_coefficient' => [0.0, 1.0, 'Le supplément par charnière doit être compris entre 0 et 1 (0,05 = 5 %)'],
        'max_width' => [50.0, 5000.0, 'La largeur maximale doit être comprise entre 50 et 5 000 mm'],
        'max_height' => [50.0, 5000.0, 'La hauteur maximale doit être comprise entre 50 et 5 000 mm'],
        'fixed_depth' => [1.0, 100.0, "L'épaisseur doit être comprise entre 1 et 100 mm"],
        'hinge_edge_margin' => [1.0, 1000.0, 'La marge des charnières doit être comprise entre 1 et 1 000 mm'],
        'hinge_hole_diameter' => [1.0, 100.0, 'Le diamètre des trous doit être compris entre 1 et 100 mm'],
    ];

    public function __construct(private readonly FacadeSettingRepository $settings) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('facade-settings', errorStyle: ErrorStyle::Message);
        $script->get(null, fn(): Response => Response::json(['success' => true, 'data' => $this->settings->all()]));
        $script->put(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['setting_key'], $data['setting_value'])) {
                throw new DomainException('setting_key et setting_value sont requis');
            }
            $key = (string) $data['setting_key'];
            $limits = self::LIMITS[$key] ?? null;
            if ($limits === null && $this->settings->findByKey($key) === null) {
                throw new NotFoundException('Paramètre non trouvé');
            }
            $value = $limits === null ? (string) $data['setting_value'] : self::validated($data['setting_value'], $limits);
            $this->settings->updateValue($key, $value);
            return Response::json(['success' => true, 'message' => 'Paramètre mis à jour avec succès']);
        }, [new AdminGuard()]);
    }

    private static function validated(mixed $raw, array $limits): string
    {
        [$min, $max, $message] = $limits;
        $value = is_string($raw) ? str_replace(',', '.', trim($raw)) : $raw;
        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            throw new DomainException($message);
        }
        return (string) (float) $value;
    }
}
