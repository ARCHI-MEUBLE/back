<?php

declare(strict_types=1);

namespace App\Domain\QuoteRequest;

use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Shared\DomainException;
use App\Infrastructure\Mail\EmailGateway;

final class QuoteRequestService
{
    // Extension enregistrée => nature attendue. Liste fermée : un .svg ou .html servi sur notre domaine pourrait exécuter du script
    private const ALLOWED_EXTENSIONS = [
        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
        'avif' => 'image', 'heic' => 'image', 'heif' => 'image',
        'mp4' => 'video', 'm4v' => 'video', 'mov' => 'video', 'avi' => 'video', 'webm' => 'video',
    ];

    public function __construct(
        private readonly QuoteRequestRepository $quotes,
        private readonly AdminNotificationRepository $notifications,
        private readonly EmailGateway $mail,
        private readonly string $uploadsDir,
    ) {}

    public function submit(array $form, array $files): array
    {
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            if (!isset($form[$field]) || $form[$field] === '') {
                throw new DomainException('Tous les champs obligatoires doivent être remplis');
            }
        }
        $incoming = $files['files'] ?? null;
        if (!is_array($incoming) || $incoming === [] || $incoming['name'] === []) {
            throw new DomainException("Aucun fichier n'a été envoyé");
        }
        $quoteId = $this->quotes->create((string) $form['first_name'], (string) $form['last_name'], (string) $form['email'], (string) $form['phone'], (string) ($form['description'] ?? ''));
        $uploaded = $this->storeFiles($quoteId, $incoming);
        $this->notifications->notifyAllAdmins(
            'new_quote_request',
            sprintf('Demande de devis de %s %s avec %d fichier(s)', $form['first_name'], $form['last_name'], count($uploaded)),
        );
        $this->mail->sendNewQuoteRequestNotification(
            (string) $form['first_name'],
            (string) $form['last_name'],
            (string) $form['email'],
            (string) $form['phone'],
            (string) ($form['description'] ?? ''),
            count($uploaded),
        );
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
            mkdir($this->uploadsDir, 0o777, true);
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
            // Le type envoyé par le navigateur se falsifie : on lit le contenu réel du fichier
            if ($kind === null || $size > 10 * 1024 * 1024 || !$this->contentMatches($tmpName, $kind, $extension)) {
                continue;
            }
            $storedName = uniqid('quote_' . $quoteId . '_', true) . '.' . $extension;
            if (!move_uploaded_file($tmpName, $this->uploadsDir . '/' . $storedName)) {
                continue;
            }
            $this->quotes->addFile($quoteId, (string) $name, $storedName, $kind, $size);
            $uploaded[] = ['name' => $name, 'type' => $kind, 'size' => $size];
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
        // Les anciennes versions de libmagic ne reconnaissent pas le HEIC/HEIF
        return in_array($extension, ['heic', 'heif'], true) && $detected === 'application/octet-stream';
    }
}
