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
        $output = shell_exec('pg_dump ' . escapeshellarg($this->databaseUrl) . ' > ' . escapeshellarg($file) . ' 2>&1');
        if (is_file($file) && filesize($file) > 0) {
            return ['ok' => true, 'filename' => basename($file), 'size' => round(filesize($file) / 1024 / 1024, 2) . ' MB'];
        }
        if (is_file($file)) {
            unlink($file);
        }
        return ['ok' => false, 'output' => is_string($output) ? $output : ''];
    }

    public function isValidFilename(string $filename): bool
    {
        return preg_match('/^database-backup-[\d_-]+\.sql$/', $filename) === 1;
    }

    public function path(string $filename): string
    {
        return $this->dir . '/' . $filename;
    }

    public function restore(string $filename): array
    {
        $backupPath = $this->path($filename);
        $emergencyFile = $this->dir . '/database-backup-before-restore-' . date('Y-m-d_H-i-s') . '.sql';
        shell_exec('pg_dump ' . escapeshellarg($this->databaseUrl) . ' > ' . escapeshellarg($emergencyFile) . ' 2>&1');
        shell_exec('psql ' . escapeshellarg($this->databaseUrl) . ' < ' . escapeshellarg($backupPath) . ' 2>&1');
        return ['restored_from' => $filename, 'emergency_backup' => basename($emergencyFile)];
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }
}
