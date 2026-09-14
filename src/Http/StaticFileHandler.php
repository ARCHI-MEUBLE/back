<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\PathSettings;

final class StaticFileHandler
{
    private const TYPES = [
        'css' => 'text/css', 'js' => 'application/javascript', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'glb' => 'model/gltf-binary', 'gltf' => 'model/gltf+json', 'ico' => 'image/x-icon', 'woff' => 'font/woff',
        'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject', 'dxf' => 'application/dxf',
        'pdf' => 'application/pdf', 'mp4' => 'video/mp4',
    ];

    public function __construct(private readonly PathSettings $paths) {}

    public function handle(Request $request): ?Response
    {
        $extension = strtolower(pathinfo($request->path, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$extension]) || !in_array($request->method, ['GET', 'HEAD'], true)) {
            return null;
        }
        $file = $this->locate($request->path);
        if ($file === null) {
            return Response::json(['error' => 'Fichier non trouvé'], 404);
        }
        return Response::file($file, self::TYPES[$extension])
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }

    public function locate(string $path): ?string
    {
        foreach ($this->candidates($path) as [$root, $relative]) {
            $resolved = realpath($root . '/' . $relative);
            $rootReal = realpath($root);
            if ($resolved !== false && $rootReal !== false && str_starts_with($resolved, $rootReal . '/') && is_file($resolved)) {
                return $resolved;
            }
        }
        return null;
    }

    private function candidates(string $path): array
    {
        if (str_starts_with($path, 'backend/uploads/')) {
            $path = substr($path, strlen('backend/'));
        }
        if (str_starts_with($path, 'back/textures/')) {
            $path = substr($path, strlen('back/'));
        }
        $prefixes = [
            'uploads/' => [$this->paths->uploadsDir, '/data/uploads', $this->paths->legacyUploadsDir, $this->paths->rootDir . '/uploads'],
            'models/' => [$this->paths->modelsDir, '/data/models', $this->paths->rootDir . '/models'],
            'textures/' => [$this->paths->texturesDir],
            'backend/api/calendly/assets/' => [$this->paths->emailAssetsDir],
            'assets/' => [$this->paths->rootDir . '/assets'],
        ];
        foreach ($prefixes as $prefix => $roots) {
            if (!str_starts_with($path, $prefix)) {
                continue;
            }
            $relative = substr($path, strlen($prefix));
            if ($prefix === 'assets/') {
                return [[$this->paths->rootDir . '/assets', $relative]];
            }
            return array_map(static fn(string $root): array => [$root, $relative], $roots);
        }
        return [];
    }
}
