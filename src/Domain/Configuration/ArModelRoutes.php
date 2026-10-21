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

// Vue en réalité augmentée d'un meuble : le configurateur exporte sa propre scène 3D en GLB (portes et tiroirs
// fermés, poignées comprises) et la dépose ici. Le fichier est ensuite servi sous /models, à une adresse que le
// téléphone télécharge lui-même (Scene Viewer sur Android) ou qu'ouvre le QR code affiché sur ordinateur.
//
// Le nom du fichier vient de son contenu : déposer deux fois le même meuble ne crée qu'un fichier. Un fichier déposé
// il y a plus de RETENTION_DAYS jours, et pas redéposé depuis, est supprimé (le QR code qui y menait affiche alors
// « lien expiré »).
final class ArModelRoutes
{
    // Un meuble exporté pèse quelques centaines de Ko, textures comprises
    private const MAX_BYTES = 15 * 1024 * 1024;
    // Fichiers déposés sans compte : limite par adresse IP, pour que le dossier ne serve pas de stockage gratuit
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

        // Même meuble déjà déposé : rien à écrire ni à compter, on repousse seulement sa date d'expiration
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
        // Compte chaque dépôt (et pas seulement les échecs, comme pour une connexion) : la limite porte sur le volume
        $this->rateLimiter->hit($ip, 'ar_model_upload', false, self::MAX_UPLOADS_PER_HOUR, 60);
        $this->logger->info('ar-model', ['filename' => $filename, 'bytes' => strlen($body)]);
        $this->cleanup($dir);

        return Response::json(['success' => true, 'glb_url' => '/models/' . $filename], 201);
    }

    private function cleanup(string $dir): void
    {
        // Le ménage parcourt le dossier : une fois par jour au plus, au moment d'un dépôt. Le repère est rangé hors du
        // dossier des modèles (en local, ce dossier est le public/models du front).
        $marker = sys_get_temp_dir() . '/archimeuble-ar-cleanup-' . md5($dir);
        $lastRun = is_file($marker) ? filemtime($marker) : false;
        if ($lastRun !== false && $lastRun > time() - 86400) {
            return;
        }
        touch($marker);

        $limit = time() - self::RETENTION_DAYS * 86400;
        $removed = 0;
        foreach (glob($dir . '/ar_*.glb') ?: [] as $file) {
            $modified = filemtime($file);
            if ($modified !== false && $modified < $limit && @unlink($file)) {
                $removed++;
            }
        }
        if ($removed > 0) {
            $this->logger->info('ar-model cleanup', ['removed' => $removed]);
        }
    }

    // En-tête GLB 2.0 : « glTF », version 2, longueur totale égale à la taille reçue
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
