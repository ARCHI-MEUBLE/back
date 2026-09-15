<?php

declare(strict_types=1);

namespace App\Domain\Review;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Guard\AdminSessionForbiddenGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class ReviewRoutes
{
    public function __construct(private readonly ReviewRepository $reviews) {}

    public function register(RouteCollection $routes): void
    {
        $public = $routes->script('avis');
        $public->get(null, fn(): Response => Response::json(array_map(self::publicShape(...), $this->reviews->publicList())));
        $public->post(null, function (Request $r, Session $s): Response {
            $input = $r->json();
            if (!isset($input['rating']) || !isset($input['text'])) {
                throw new DomainException('Rating et texte sont requis');
            }
            $rating = (int) $input['rating'];
            if ($rating < 1 || $rating > 5) {
                throw new DomainException('Le rating doit être entre 1 et 5');
            }
            $text = trim((string) $input['text']);
            if ($text === '') {
                throw new DomainException("Le texte de l'avis ne peut pas être vide");
            }
            $authorName = trim((string) ($input['authorName'] ?? 'Utilisateur'));
            $date = (string) ($input['date'] ?? date('Y-m-d'));
            $id = $this->reviews->create($s->string('user_id'), $authorName, $rating, $text, $date);
            return Response::json(['success' => true, 'id' => (string) $id, 'authorName' => $authorName, 'rating' => $rating, 'text' => $text, 'date' => $date], 201);
        });
        $public->delete('/{id}', function (Request $r): Response {
            $id = (int) $r->param('id');
            if (!$this->reviews->exists($id)) {
                throw new NotFoundException('Avis non trouvé');
            }
            $this->reviews->delete($id);
            return Response::json(['success' => true, 'message' => 'Avis supprimé avec succès']);
        }, [new AdminSessionForbiddenGuard('Accès interdit. Vous devez être administrateur.')]);

        $routes->script('admin/avis', [new AdminGuard()], ErrorStyle::Plain)
            ->get(null, function (): Response {
                $reviews = $this->reviews->adminList();
                return Response::json(['success' => true, 'avis' => $reviews, 'total' => count($reviews)]);
            })
            ->delete(null, function (Request $r): Response {
                $input = $r->json();
                if (!isset($input['id'])) {
                    throw new DomainException('ID requis');
                }
                $this->reviews->delete((int) $input['id']);
                return Response::json(['success' => true, 'message' => 'Avis supprimé avec succès']);
            });
    }

    private static function publicShape(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'authorName' => $row['author_name'],
            'rating' => (int) $row['rating'],
            'text' => $row['text'],
            'date' => $row['date'],
        ];
    }
}
