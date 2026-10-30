<?php

declare(strict_types=1);

namespace App\Domain\Asset;

use App\Domain\Shared\DomainException;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class Base64ImageUploadRoutes
{
    private const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

    public function __construct(private readonly string $modelsUploadDir) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('upload', [new AdminGuard('Unauthorized')])->post(null, function (Request $r): Response {
            $data = $r->json();
            if (!isset($data['fileName'], $data['fileType'], $data['data'])) {
                throw new DomainException('File payload is incomplete');
            }
            if (!in_array($data['fileType'], self::ALLOWED_TYPES, true)) {
                throw new DomainException('Unsupported file type');
            }
            $raw = (string) $data['data'];
            $raw = str_contains($raw, ',') ? explode(',', $raw, 2)[1] : $raw;
            $imageData = base64_decode($raw, true);
            if ($imageData === false) {
                throw new DomainException('Invalid base64 data');
            }
            $extension = $data['fileType'] === 'image/png' ? 'png' : 'jpg';
            $uniqueName = time() . '-' . uniqid() . '.' . $extension;
            if (!is_dir($this->modelsUploadDir)) {
                mkdir($this->modelsUploadDir, 0o755, true);
            }
            if (file_put_contents($this->modelsUploadDir . '/' . $uniqueName, $imageData) === false) {
                throw new DomainException('Failed to save file', 500);
            }
            return Response::json(['success' => true, 'imagePath' => self::absoluteUrl($r->host, $uniqueName)]);
        });
    }

    private static function absoluteUrl(string $host, string $fileName): string
    {
        $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
        return ($isLocal ? 'http' : 'https') . '://' . $host . '/uploads/models/' . $fileName;
    }
}
