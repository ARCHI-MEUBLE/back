<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\ConflictException;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminConfigurationPriceRoutes
{
    private const MAX_PRICE = 100000;
    private const MAX_NOTE_LENGTH = 500;

    public function __construct(private readonly ConfigurationRepository $configurations) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/configurations/price', [new AdminGuard()], ErrorStyle::Success)->post(null, function (Request $r): Response {
            $data = $r->jsonOrEmpty();
            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                throw new DomainException('ID de configuration invalide');
            }
            $price = is_string($data['price'] ?? null) ? str_replace(',', '.', trim($data['price'])) : ($data['price'] ?? null);
            if (!is_numeric($price) || (float) $price <= 0 || (float) $price > self::MAX_PRICE) {
                throw new DomainException('Le prix doit être compris entre 0,01 et 100 000 €');
            }
            $note = is_scalar($data['note'] ?? null) ? trim(strip_tags((string) $data['note'])) : '';
            if (mb_strlen($note) > self::MAX_NOTE_LENGTH) {
                throw new DomainException('La note ne doit pas dépasser 500 caractères');
            }
            $configuration = $this->configurations->findWithOrderId($id);
            if ($configuration === null) {
                throw new NotFoundException('Configuration introuvable');
            }
            if (in_array($configuration['status'], ['en_commande', 'payee'], true)) {
                throw new ConflictException('Cette configuration est déjà commandée : son prix ne peut plus être modifié');
            }
            $rounded = round((float) $price, 2);
            $this->configurations->update($id, ['price' => $rounded, 'price_adjusted' => true, 'price_note' => $note === '' ? null : $note]);
            return Response::json(['success' => true, 'price' => $rounded]);
        });
    }
}
