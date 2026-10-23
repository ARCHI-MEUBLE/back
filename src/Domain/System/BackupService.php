<?php

declare(strict_types=1);

namespace App\Domain\System;

final class BackupService
{
    public function __construct(
        private readonly string $dir,
        private readonly string $databaseUrl,
    ) {}

    public function list(): array
    {
        $this->ensureDir();
        $found = glob($this->dir . '/database-backup-*.sql');
        $files = $found === false ? [] : $found;
        $backups = array_map(static function (string $file): array {
            $size = (int) filesize($file);
            $date = (int) filemtime($file);
            return [
                'filename' => basename($file),
                'size' => round($size / 1024 / 1024, 2) . ' MB',
                'size_bytes' => $size,
                'date' => date('Y-m-d H:i:s', $date),
                'timestamp' => $date,
            ];
        }, $files);
        usort($backups, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);
        return $backups;
    }

    public function create(): array
    {
        $this->ensureDir();
        $file = $this->dir . '/database-backup-' . date('Y-m-d_H-i-s') . '.sql';
        [$code, $output] = $this->dump($file);
        if ($code === 0 && is_file($file) && filesize($file) > 0) {
            return ['ok' => true, 'filename' => basename($file), 'size' => round(filesize($file) / 1024 / 1024, 2) . ' MB'];
        }
        if (is_file($file)) {
            unlink($file);
        }
        return ['ok' => false, 'output' => $output];
    }

    public function isValidFilename(string $filename): bool
    {
        return preg_match('/^database-backup-[A-Za-z0-9_-]+\.sql$/', $filename) === 1;
    }

    public function path(string $filename): string
    {
        return $this->dir . '/' . $filename;
    }

    public function restore(string $filename): array
    {
        $emergencyFile = $this->dir . '/database-backup-' . date('Y-m-d_H-i-s') . '-pre-restore.sql';
        [$dumpCode, $dumpOutput] = $this->dump($emergencyFile);
        if ($dumpCode !== 0) {
            return ['ok' => false, 'output' => 'Sauvegarde de sécurité impossible, restauration annulée: ' . $dumpOutput];
        }
        [$code, $output] = $this->run(sprintf(
            "sed -e '/^SET transaction_timeout = /d' %s | psql --single-transaction -v ON_ERROR_STOP=1 -q %s 2>&1",
            escapeshellarg($this->path($filename)),
            escapeshellarg($this->databaseUrl),
        ));
        return ['ok' => $code === 0, 'output' => $output, 'restored_from' => $filename, 'emergency_backup' => basename($emergencyFile)];
    }

    private function dump(string $file): array
    {
        return $this->run(sprintf('pg_dump --clean --if-exists --no-owner %s -f %s 2>&1', escapeshellarg($this->databaseUrl), escapeshellarg($file)));
    }

    private function run(string $command): array
    {
        $output = [];
        $code = 1;
        exec($command, $output, $code);
        return [$code, substr(implode("\n", $output), -2000)];
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0o700, true);
        }
    }
}
