<?php

declare(strict_types=1);

namespace App\Domain\Asset;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminImageUploadRoutes
{
    private const ALLOWED_EXTENSIONS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    private const DANGEROUS_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'js', 'html', 'htm', 'svg', 'exe', 'sh', 'bat'];
    private const MAX_SIZE = 10 * 1024 * 1024;
    private const MAX_DIMENSION = 8000;

    public function __construct(private readonly string $dataDir, private readonly string $localDir) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/upload-image', [new AdminGuard()], ErrorStyle::Success)->post(null, fn(Request $r): Response => $this->handle($r));
    }

    private function handle(Request $r): Response
    {
        $file = $r->files['image'] ?? null;
        if (!is_array($file)) {
            throw new DomainException('Aucune image envoyée');
        }
        self::assertUploadOk((int) $file['error']);
        $size = (int) $file['size'];
        if ($size > self::MAX_SIZE) {
            throw new DomainException('Fichier trop volumineux (max 10Mo)');
        }
        $extension = self::resolveExtension((string) $file['name'], (string) $file['tmp_name']);
        self::assertValidImage((string) $file['tmp_name']);
        $useData = is_dir($this->dataDir) && is_writable($this->dataDir);
        $uploadDir = $useData ? $this->dataDir . '/uploads/catalogue' : $this->localDir . '/catalogue';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0o755, true);
        }
        $newFileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destPath = $uploadDir . '/' . $newFileName;
        if (!copy((string) $file['tmp_name'], $destPath)) {
            throw new DomainException('Impossible de sauvegarder le fichier sur le serveur', 500);
        }
        @unlink((string) $file['tmp_name']);
        chmod($destPath, 0o644);
        $url = ($useData ? '/uploads/catalogue/' : '/backend/uploads/catalogue/') . $newFileName;
        return Response::json(['success' => true, 'url' => $url, 'filename' => $newFileName]);
    }

    private static function assertUploadOk(int $error): void
    {
        if ($error === 0) {
            return;
        }
        throw new DomainException($error === 1 || $error === 2 ? 'Le fichier est trop volumineux (max 10Mo)' : 'Erreur lors du transfert du fichier');
    }

    private static function resolveExtension(string $fileName, string $tmpName): string
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        foreach (self::DANGEROUS_EXTENSIONS as $dangerous) {
            if (preg_match('/\.' . preg_quote($dangerous, '/') . '$/i', $baseName) === 1) {
                throw new DomainException('Nom de fichier non autorisé');
            }
        }
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, self::ALLOWED_EXTENSIONS)) {
            throw new DomainException('Format non autorisé (JPG, PNG, WEBP uniquement)');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new DomainException('Server error', 500);
        }
        $detectedMime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        $allowedMimes = array_unique(array_values(self::ALLOWED_EXTENSIONS));
        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new DomainException('Type de fichier non autorisé (MIME: ' . $detectedMime . ')');
        }
        $mimeToExtension = array_flip(self::ALLOWED_EXTENSIONS);
        return (self::ALLOWED_EXTENSIONS[$extension] !== $detectedMime) ? $mimeToExtension[$detectedMime] : $extension;
    }

    private static function assertValidImage(string $tmpName): void
    {
        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            throw new DomainException("Le fichier n'est pas une image valide");
        }
        if ($imageInfo[0] > self::MAX_DIMENSION || $imageInfo[1] > self::MAX_DIMENSION) {
            throw new DomainException(sprintf('Image trop grande (max %dx%d pixels)', self::MAX_DIMENSION, self::MAX_DIMENSION));
        }
    }
}
