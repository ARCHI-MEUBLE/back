<?php

declare(strict_types=1);

namespace App\Domain\Asset;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class TextureUploadRoutes
{
    private const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

    public function __construct(private readonly string $texturesDir) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('upload-texture', [new AdminGuard()], ErrorStyle::Success)->post(null, function (Request $r): Response {
            $file = $r->files['file'] ?? null;
            if (!is_array($file)) {
                throw new DomainException('Aucun fichier reçu');
            }
            if (!in_array((string) $file['type'], self::ALLOWED_TYPES, true)) {
                throw new DomainException('Type de fichier non supporté');
            }
            if (!is_dir($this->texturesDir)) {
                @mkdir($this->texturesDir, 0o777, true);
            }
            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $fileName = 'texture_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            if (!move_uploaded_file((string) $file['tmp_name'], $this->texturesDir . '/' . $fileName)) {
                throw new DomainException("Échec de l'upload", 500);
            }
            return Response::json(['success' => true, 'url' => '/textures/' . $fileName]);
        });
    }
}
