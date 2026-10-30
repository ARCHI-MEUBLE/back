<?php

declare(strict_types=1);

namespace App\Domain\Config;

use App\Config\Env;
use App\Http\Response;
use App\Http\RouteCollection;

final class PublicConfigRoutes
{
    public function __construct(private readonly Env $env) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('config')->get(null, fn(): Response => Response::json([
            'calendly' => [
                'phoneUrl' => $this->env->string('CALENDLY_PHONE_URL', ''),
                'visioUrl' => $this->env->string('CALENDLY_VISIO_URL', ''),
            ],
            'crisp' => ['websiteId' => $this->env->string('CRISP_WEBSITE_ID', '')],
        ]));
    }
}
