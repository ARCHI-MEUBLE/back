<?php

declare(strict_types=1);

namespace App\Config;

final class PathSettings
{
    public function __construct(
        public readonly string $rootDir,
        public readonly string $modelsDir,
        public readonly string $uploadsDir,
        public readonly string $texturesDir,
        public readonly string $emailAssetsDir,
        public readonly string $legacyUploadsDir,
        public readonly string $backupsDir,
    ) {}

    public static function fromEnv(Env $env, string $rootDir): self
    {
        $dataDir = is_dir('/data') ? '/data' : null;
        $models = $env->string('MODELS_DIR') ?? $env->string('OUTPUT_DIR') ?? ($dataDir === null ? $rootDir . '/storage/models' : $dataDir . '/models');
        $uploads = $env->string('UPLOADS_DIR') ?? ($dataDir === null ? $rootDir . '/storage/uploads' : $dataDir . '/uploads');
        $backups = $dataDir === null ? $rootDir . '/storage/backups' : $dataDir . '/backups';
        return new self(
            $rootDir,
            rtrim($models, '/'),
            rtrim($uploads, '/'),
            rtrim($env->string('TEXTURES_DIR') ?? $rootDir . '/assets/textures', '/'),
            $rootDir . '/legacy/calendly/assets',
            $rootDir . '/backend/uploads',
            rtrim($backups, '/'),
        );
    }
}
