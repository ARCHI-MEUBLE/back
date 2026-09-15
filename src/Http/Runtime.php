<?php

declare(strict_types=1);

namespace App\Http;

use App\Lib\Logger;
use ErrorException;

final class Runtime
{
    public static function configure(Logger $logger): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', 'php://stderr');
        error_reporting(E_ALL);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        register_shutdown_function(static function () use ($logger): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            $logger->error('fatal error', ['message' => $error['message'], 'file' => $error['file'], 'line' => $error['line']]);
            if (!headers_sent()) {
                Response::json(['error' => 'Erreur serveur'], 500)->send();
            }
        });
    }
}
