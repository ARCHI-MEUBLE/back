<?php

declare(strict_types=1);

namespace App\Domain\QuoteRequest;

use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Shared\DomainException;

final class QuoteRequestService
{
    public function __construct(
        private readonly QuoteRequestRepository $quotes,
        private readonly AdminNotificationRepository $notifications,
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
        return ['quote_request_id' => $quoteId, 'uploaded_files' => $uploaded];
    }

    public function recent(int $limit): array
    {
        return $this->quotes->recent($limit);
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
            $type = (string) $files['type'][$i];
            $isImage = str_starts_with($type, 'image/');
            $isVideo = str_starts_with($type, 'video/');
            $size = (int) $files['size'][$i];
            if ((!$isImage && !$isVideo) || $size > 10 * 1024 * 1024) {
                continue;
            }
            $extension = pathinfo((string) $name, PATHINFO_EXTENSION);
            $storedName = uniqid('quote_' . $quoteId . '_', true) . '.' . $extension;
            if (!move_uploaded_file((string) $files['tmp_name'][$i], $this->uploadsDir . '/' . $storedName)) {
                continue;
            }
            $this->quotes->addFile($quoteId, (string) $name, $storedName, $isImage ? 'image' : 'video', $size);
            $uploaded[] = ['name' => $name, 'type' => $isImage ? 'image' : 'video', 'size' => $size];
        }
        return $uploaded;
    }
}
