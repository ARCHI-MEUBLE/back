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
    private const EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    private const MAX_SIZE = 10 * 1024 * 1024;

    public function __construct(private readonly string $texturesDir) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('upload-texture', [new AdminGuard()], ErrorStyle::Success)->post(null, function (Request $r): Response {
            $file = $r->files['file'] ?? null;
            if (!is_array($file)) {
                throw new DomainException('Aucun fichier reçu');
            }
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > self::MAX_SIZE) {
                throw new DomainException('Fichier invalide ou trop volumineux (max 10 Mo)');
            }
            $extension = self::extension((string) $file['tmp_name']);
            if (!is_dir($this->texturesDir)) {
                @mkdir($this->texturesDir, 0o755, true);
            }
            $fileName = 'texture_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            if (!move_uploaded_file((string) $file['tmp_name'], $this->texturesDir . '/' . $fileName)) {
                throw new DomainException("Échec de l'upload", 500);
            }
            return Response::json(['success' => true, 'url' => '/textures/' . $fileName]);
        });
    }

    private static function extension(string $tmpName): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_file($finfo, $tmpName);
        if (!is_string($mime) || !isset(self::EXTENSIONS[$mime]) || @getimagesize($tmpName) === false) {
            throw new DomainException('Type de fichier non supporté');
        }
        return self::EXTENSIONS[$mime];
    }
}
