<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminPaymentLinksRoutes
{
    public function __construct(
        private readonly PaymentLinkRepository $links,
        private readonly string $frontendUrl,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('admin/payment-links', [new AdminGuard()], ErrorStyle::Success);
        $script->get(null, fn(Request $r): Response => $this->list($r));
        $script->post(null, fn(Request $r): Response => $this->revoke($r));
    }

    private function list(Request $r): Response
    {
        $orderId = $r->queryString('order_id');
        if ($orderId === null) {
            return Response::json(['success' => false, 'error' => 'Méthode non autorisée'], 405);
        }
        $links = array_map(function (array $link): array {
            $link['url'] = rtrim($this->frontendUrl, '/') . '/paiement/' . $link['token'];
            return $link;
        }, $this->links->getLinksByOrderId((int) $orderId));
        return Response::json(['success' => true, 'links' => $links]);
    }

    private function revoke(Request $r): Response
    {
        $data = $r->jsonOrEmpty();
        if (!isset($data['action']) || $data['action'] !== 'revoke') {
            throw new DomainException('Action invalide');
        }
        if (!isset($data['link_id'])) {
            throw new DomainException('ID de lien manquant');
        }
        $linkId = (int) $data['link_id'];
        if ($this->links->findById($linkId) === null) {
            throw new NotFoundException('Lien introuvable');
        }
        $this->links->revokeLink($linkId);
        return Response::json(['success' => true, 'message' => 'Lien révoqué avec succès']);
    }
}
