<?php

declare(strict_types=1);

namespace App\Domain\QuoteRequest;

use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Shared\DomainException;
use App\Infrastructure\Mail\EmailGateway;
use App\Infrastructure\RateLimit\Throttle;

final class QuoteRequestService
{
    private const ALLOWED_EXTENSIONS = [
        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
        'avif' => 'image', 'heic' => 'image', 'heif' => 'image',
        'mp4' => 'video', 'm4v' => 'video', 'mov' => 'video', 'avi' => 'video', 'webm' => 'video',
    ];
    private const MAX_FILES = 10;
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private readonly QuoteRequestRepository $quotes,
        private readonly AdminNotificationRepository $notifications,
        private readonly EmailGateway $mail,
        private readonly string $uploadsDir,
        private readonly Throttle $throttle,
    ) {}

    public function submit(array $form, array $files, string $ip): array
    {
        $fields = LeadForm::fields($form, ['first_name' => 100, 'last_name' => 100, 'email' => 200, 'phone' => 30], ['description' => 5000]);
        $incoming = $files['files'] ?? null;
        if (!is_array($incoming) || $incoming === [] || !is_array($incoming['name'] ?? null) || $incoming['name'] === []) {
            throw new DomainException("Aucun fichier n'a été envoyé");
        }
        if (count($incoming['name']) > self::MAX_FILES) {
            throw new DomainException(sprintf('%d fichiers maximum', self::MAX_FILES));
        }
        $this->throttle->consume($ip, 'quote_request_ip', 5, 60);
        $quoteId = $this->quotes->create($fields['first_name'], $fields['last_name'], $fields['email'], $fields['phone'], $fields['description']);
        $uploaded = $this->storeFiles($quoteId, $incoming);
        $this->notifications->notifyAllAdmins(
            'new_quote_request',
            sprintf('Demande de devis de %s %s avec %d fichier(s)', $fields['first_name'], $fields['last_name'], count($uploaded)),
        );
        $this->mail->sendNewQuoteRequestNotification($fields['first_name'], $fields['last_name'], $fields['email'], $fields['phone'], $fields['description'], count($uploaded));
        return ['quote_request_id' => $quoteId, 'uploaded_files' => $uploaded];
    }

    public function recent(int $limit): array
    {
        $requests = $this->quotes->recent($limit);
        $ids = array_map(static fn(array $row): int => (int) $row['id'], $requests);
        $filesByRequest = $this->quotes->filesFor($ids);
        return array_map(static function (array $row) use ($filesByRequest): array {
            $files = $filesByRequest[(int) $row['id']] ?? [];
            $row['files'] = array_map(static fn(array $file): array => [
                'name' => $file['file_name'],
                'type' => $file['file_type'],
                'size' => (int) $file['file_size'],
                'url' => '/uploads/quote-requests/' . $file['file_path'],
            ], $files);
            return $row;
        }, $requests);
    }

    private function storeFiles(int $quoteId, array $files): array
    {
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0o755, true);
        }
        $uploaded = [];
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $size = (int) $files['size'][$i];
            $tmpName = (string) $files['tmp_name'][$i];
            $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
            $kind = self::ALLOWED_EXTENSIONS[$extension] ?? null;
            if ($kind === null || $size > self::MAX_FILE_SIZE || !$this->contentMatches($tmpName, $kind, $extension)) {
                continue;
            }
            $storedName = 'quote_' . $quoteId . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
            if (!move_uploaded_file($tmpName, $this->uploadsDir . '/' . $storedName)) {
                continue;
            }
            $displayName = mb_substr(basename((string) $name), 0, 200);
            $this->quotes->addFile($quoteId, $displayName, $storedName, $kind, $size);
            $uploaded[] = ['name' => $displayName, 'type' => $kind, 'size' => $size];
        }
        return $uploaded;
    }

    private function contentMatches(string $tmpName, string $kind, string $extension): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }
        $detected = (string) finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        if ($detected === 'image/svg+xml') {
            return false;
        }
        if (str_starts_with($detected, $kind . '/')) {
            return true;
        }
        return in_array($extension, ['heic', 'heif'], true) && $detected === 'application/octet-stream';
    }
}
