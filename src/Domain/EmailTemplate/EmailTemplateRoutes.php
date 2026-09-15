<?php

declare(strict_types=1);

namespace App\Domain\EmailTemplate;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class EmailTemplateRoutes
{
    public function __construct(private readonly EmailTemplateRepository $templates) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/email-templates', [new AdminGuard()])
            ->get(null, function (): Response {
                $templates = array_map(
                    static fn(array $t): array => $t + ['gallery_images_array' => EmailTemplateRepository::galleryImages($t)],
                    $this->templates->all(),
                );
                return Response::json(['success' => true, 'templates' => $templates]);
            })
            ->put(null, function (Request $r): Response {
                $data = $r->json();
                if (!isset($data['id'])) {
                    throw new DomainException('ID du template manquant');
                }
                if ($this->templates->findById((int) $data['id']) === null) {
                    throw new NotFoundException('Template non trouvé');
                }
                $this->templates->update((int) $data['id'], $data);
                return Response::json(['success' => true, 'message' => 'Template mis à jour avec succès']);
            });
    }
}
