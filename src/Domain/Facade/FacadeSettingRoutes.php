<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class FacadeSettingRoutes
{
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
            $this->settings->updateValue((string) $data['setting_key'], (string) $data['setting_value']);
            return Response::json(['success' => true, 'message' => 'Paramètre mis à jour avec succès']);
        }, [new AdminGuard()]);
    }
}
