<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Python\ProcOpenRunner;
use App\Lib\Logger;

final class GenerateRoutes
{
    private const PROMPT_PATTERN = '/^M[1-5]\(\d+,\d+,\d+(,\d+)?\)[A-Za-z0-9\(\),\[\]]+$/';

    public function __construct(
        private readonly ProcOpenRunner $runner,
        private readonly Logger $logger,
        private readonly string $modelsDir,
        private readonly string $pythonScript,
        private readonly string $pythonBin,
        private readonly int $timeoutSeconds,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('generate', errorStyle: ErrorStyle::Success)->post(null, fn(Request $r): Response => $this->generate($r));
    }

    private function generate(Request $r): Response
    {
        $data = $r->jsonOrEmpty();
        if (!isset($data['prompt']) || $data['prompt'] === '') {
            throw new DomainException('Le paramètre "prompt" est requis');
        }
        $prompt = trim((string) $data['prompt']);
        if (preg_match(self::PROMPT_PATTERN, $prompt) !== 1) {
            throw new DomainException('Format de prompt invalide. Attendu : M[1-5](largeur,profondeur,hauteur)MODULES(...)');
        }
        if (strlen($prompt) > 200) {
            throw new DomainException('Prompt trop long (max 200 caractères)');
        }
        if (!is_file($this->pythonScript)) {
            throw new DomainException('Script Python introuvable', 500);
        }
        $filename = 'meuble_' . uniqid() . '.glb';
        if (!is_dir($this->modelsDir) && !mkdir($this->modelsDir, 0o755, true) && !is_dir($this->modelsDir)) {
            throw new DomainException('Impossible de créer le dossier de sortie', 500);
        }
        $outputPath = rtrim($this->modelsDir, '/') . '/' . $filename;
        $result = $this->runner->run($this->argv($prompt, $outputPath, $data), dirname($this->pythonScript), self::pythonEnv(), $this->timeoutSeconds);
        $this->logger->info('generate', ['prompt' => $prompt, 'duration_s' => $result->durationSeconds, 'exit_code' => $result->exitCode, 'timed_out' => $result->timedOut]);
        if (!$result->succeeded()) {
            $this->logger->error('generate failed', ['output' => $result->output]);
            throw new DomainException('Erreur lors de la génération du meuble 3D', 500);
        }
        if (!is_file($outputPath)) {
            $this->logger->error('generate: glb missing', ['output_path' => $outputPath, 'python_output' => $result->output]);
            throw new DomainException("Le fichier GLB n'a pas été généré", 500);
        }
        $dxfFilename = pathinfo($filename, PATHINFO_FILENAME) . '.dxf';
        $dxfUrl = is_file(rtrim($this->modelsDir, '/') . '/' . $dxfFilename) ? '/models/' . $dxfFilename : null;
        return Response::json([
            'success' => true,
            'glb_url' => '/models/' . $filename,
            'dxf_url' => $dxfUrl,
            'prompt' => $prompt,
            'filename' => $filename,
            'execution_time' => round($result->durationSeconds, 2) . 's',
        ], 201);
    }

    private function argv(string $prompt, string $outputPath, array $data): array
    {
        $argv = [$this->pythonBin, $this->pythonScript, $prompt, $outputPath];
        if (($data['closed'] ?? false) === true) {
            $argv[] = '--closed';
        }
        $hasColor = isset($data['color']) && $data['color'] !== '';
        $colors = is_array($data['colors'] ?? null) ? $data['colors'] : ($hasColor ? ['all' => trim((string) $data['color'])] : null);
        if ($colors !== null && $colors !== []) {
            $argv[] = '--colors';
            $argv[] = json_encode($colors, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        if (is_array($data['deletedPanels'] ?? null) && $data['deletedPanels'] !== []) {
            $argv[] = '--deleted-panels';
            $argv[] = json_encode($data['deletedPanels'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        if (is_array($data['zones'] ?? null) && $data['zones'] !== []) {
            $argv[] = '--zones';
            $argv[] = json_encode($data['zones'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        return $argv;
    }

    private static function pythonEnv(): array
    {
        $env = [];
        foreach (['PATH', 'HOME', 'PYVISTA_OFF_SCREEN', 'PYVISTA_USE_IPYVTK', 'VTK_SILENCE_GET_VOID_POINTER_WARNINGS', 'MESA_GL_VERSION_OVERRIDE', 'DISPLAY', 'TZ', 'LANG'] as $name) {
            $value = getenv($name);
            if (is_string($value)) {
                $env[$name] = $value;
            }
        }
        return $env;
    }
}
