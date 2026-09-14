<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class DbMaintenanceRoutes
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly BackupAccessGuard $guard,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('system/db-maintenance');
        $script->get(null, fn(Request $r): Response => $this->list($r));
        $script->get('/list', fn(Request $r): Response => $this->list($r));
        $script->get('/download/{filename}', fn(Request $r): Response => $this->download($r));
        $script->post('/create', fn(Request $r): Response => $this->create($r));
        $script->post(null, fn(Request $r): Response => $this->restore($r));
    }

    private function list(Request $request): Response
    {
        $ip = $this->guard->check($request);
        $this->guard->log('LIST_BACKUPS', true, $ip);
        $backups = $this->backups->list();
        return Response::json(['success' => true, 'count' => count($backups), 'backups' => $backups]);
    }

    private function download(Request $request): Response
    {
        $ip = $this->guard->check($request);
        $filename = $request->param('filename') ?? '';
        if (!$this->backups->isValidFilename($filename)) {
            return Response::json(['error' => 'Invalid filename'], 400);
        }
        $path = $this->backups->path($filename);
        if (!is_file($path)) {
            return Response::json(['error' => 'Backup not found'], 404);
        }
        $this->guard->log('DOWNLOAD_BACKUP', true, $ip, "File: {$filename}");
        return Response::file($path, 'application/octet-stream', $filename);
    }

    private function create(Request $request): Response
    {
        $ip = $this->guard->check($request);
        $this->guard->log('CREATE_BACKUP', true, $ip);
        $result = $this->backups->create();
        if ($result['ok'] !== true) {
            return Response::json(['error' => 'Backup failed', 'details' => $result['output']], 500);
        }
        return Response::json(['success' => true, 'message' => 'Backup created successfully', 'filename' => $result['filename'], 'size' => $result['size']]);
    }

    private function restore(Request $request): Response
    {
        $ip = $this->guard->check($request);
        $filename = (string) ($request->json()['filename'] ?? '');
        if (!$this->backups->isValidFilename($filename)) {
            return Response::json(['error' => 'Invalid filename'], 400);
        }
        if (!is_file($this->backups->path($filename))) {
            return Response::json(['error' => 'Backup not found'], 404);
        }
        $this->guard->log('RESTORE_BACKUP', true, $ip);
        $result = $this->backups->restore($filename);
        return Response::json(['success' => true, 'message' => 'Database restored successfully'] + $result);
    }
}
