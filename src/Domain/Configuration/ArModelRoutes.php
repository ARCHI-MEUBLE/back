<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Lib\Logger;

final class ArModelRoutes
{
    private const MAX_BYTES = 15 * 1024 * 1024;
    private const MAX_UPLOADS_PER_HOUR = 60;
    private const RETENTION_DAYS = 30;
    private const GLB_MAGIC = 0x46546C67;

    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly Logger $logger,
        private readonly string $modelsDir,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('ar-model', errorStyle: ErrorStyle::Success)->post(null, fn(Request $r): Response => $this->store($r));
    }

    private function store(Request $r): Response
    {
        $body = $r->rawBody();
        if (!self::isGlb($body)) {
            throw new DomainException('Fichier 3D invalide (GLB attendu)');
        }

        $dir = rtrim($this->modelsDir, '/');
        $filename = 'ar_' . substr(hash('sha256', $body), 0, 32) . '.glb';
        $path = $dir . '/' . $filename;

        if (is_file($path)) {
            touch($path);
            return Response::json(['success' => true, 'glb_url' => '/models/' . $filename]);
        }

        $ip = RateLimiter::clientIp($_SERVER);
        $decision = $this->rateLimiter->check($ip, 'ar_model_upload', self::MAX_UPLOADS_PER_HOUR, 60);
        if (!$decision->allowed) {
            throw new DomainException('Trop de vues en réalité augmentée préparées depuis cet appareil. Réessayez dans une heure.', 429);
        }

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new DomainException('Impossible de créer le dossier de sortie', 500);
        }
        if (file_put_contents($path, $body, LOCK_EX) === false) {
            throw new DomainException("Le fichier 3D n'a pas pu être enregistré", 500);
        }
        $this->rateLimiter->hit($ip, 'ar_model_upload', false, self::MAX_UPLOADS_PER_HOUR, 60);
        $this->logger->info('ar-model', ['filename' => $filename, 'bytes' => strlen($body)]);
        $this->cleanup($dir);

        return Response::json(['success' => true, 'glb_url' => '/models/' . $filename], 201);
    }

    private function cleanup(string $dir): void
    {
        $marker = sys_get_temp_dir() . '/archimeuble-ar-cleanup-' . md5($dir);
        $lastRun = is_file($marker) ? filemtime($marker) : false;
        if ($lastRun !== false && $lastRun > time() - 86400) {
            return;
        }
        touch($marker);

        $limit = time() - self::RETENTION_DAYS * 86400;
        $removed = 0;
        $files = glob($dir . '/ar_*.glb');
        foreach ($files === false ? [] : $files as $file) {
            $modified = filemtime($file);
            if ($modified !== false && $modified < $limit && @unlink($file)) {
                $removed++;
            }
        }
        if ($removed > 0) {
            $this->logger->info('ar-model cleanup', ['removed' => $removed]);
        }
    }

    private static function isGlb(string $body): bool
    {
        $length = strlen($body);
        if ($length < 20 || $length > self::MAX_BYTES) {
            return false;
        }
        $header = unpack('Vmagic/Vversion/Vlength', substr($body, 0, 12));
        return is_array($header)
            && $header['magic'] === self::GLB_MAGIC
            && $header['version'] === 2
            && $header['length'] === $length;
    }
}
