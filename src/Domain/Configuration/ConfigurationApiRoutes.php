<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class ConfigurationApiRoutes
{
    public function __construct(private readonly ConfigurationRepository $configurations) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('configurations', errorStyle: ErrorStyle::Success);
        $script->get(null, fn(Request $r): Response => $this->get($r));
        $script->post(null, fn(Request $r): Response => $this->post($r));
    }

    private function get(Request $r): Response
    {
        if ($r->queryString('id') !== null) {
            $id = filter_var($r->queryString('id'), FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                throw new DomainException('ID invalide');
            }
            $config = $this->configurations->findWithOrderId($id);
            if ($config === null) {
                throw new NotFoundException('Configuration non trouvée');
            }
            return Response::json(['success' => true, 'data' => $config]);
        }
        $configs = $r->queryString('session') !== null
            ? $this->configurations->findBySession(htmlspecialchars($r->queryString('session'), ENT_QUOTES, 'UTF-8'))
            : $this->configurations->all();
        return Response::json(['success' => true, 'count' => count($configs), 'data' => $configs]);
    }

    private function post(Request $r): Response
    {
        $data = $r->json();
        if (!isset($data['user_session'], $data['prompt'], $data['price'])) {
            throw new DomainException('Données manquantes. Requis : user_session, prompt, price');
        }
        if (!is_numeric($data['price']) || $data['price'] < 0) {
            throw new DomainException('Prix invalide');
        }
        if (strlen((string) $data['prompt']) > 500) {
            throw new DomainException('Prompt trop long (max 500 caractères)');
        }
        $metadata = isset($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null;
        $id = $this->configurations->create(null, null, $metadata, (float) $data['price'], $data['glb_url'] ?? null, (string) $data['prompt'], (string) $data['user_session'], 'en_attente_validation');
        return Response::json(['success' => true, 'message' => 'Configuration créée avec succès', 'id' => $id], 201);
    }
}
