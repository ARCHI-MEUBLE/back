<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Customer\CustomerRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\NotFoundException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Session;
use App\Infrastructure\Mail\LegacyEmailGateway;

final class ConfigurationSaveService
{
    public function __construct(
        private readonly ConfigurationRepository $configurations,
        private readonly CustomerRepository $customers,
        private readonly LegacyEmailGateway $mail,
    ) {}

    public function save(array $data, Session $session): ConfigurationSaveResult
    {
        $isAdmin = $session->isAdmin();
        $customerId = $session->customerId();
        $isUpdate = isset($data['id']) && $data['id'];
        if (!$isUpdate && $customerId === null) {
            throw new UnauthorizedException('Vous devez être connecté en tant que client pour créer une configuration');
        }
        if ($isUpdate && !$isAdmin && $customerId === null) {
            throw new UnauthorizedException('Vous devez être connecté pour modifier cette configuration');
        }
        foreach (['prompt', 'price'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new DomainException("Le champ $field est requis");
            }
        }
        $configData = self::configData($data);
        return $isUpdate
            ? new ConfigurationSaveResult($this->update((int) $data['id'], $data, $configData, $isAdmin, $customerId), 200)
            : new ConfigurationSaveResult($this->create($data, $configData, $isAdmin, $customerId, $session), 201);
    }

    private function update(int $id, array $data, array $configData, bool $isAdmin, ?int $customerId): array
    {
        $existing = $this->configurations->findWithOrderId($id);
        if ($existing === null) {
            throw new NotFoundException('Configuration introuvable');
        }
        if (!$isAdmin && (string) $existing['user_id'] !== (string) $customerId) {
            throw new ForbiddenException('Accès refusé');
        }
        $columns = ['config_string' => json_encode($configData, JSON_THROW_ON_ERROR), 'prompt' => $data['prompt'], 'price' => $data['price']];
        $columns['status'] = !$isAdmin ? 'en_attente_validation' : ($data['status'] ?? $existing['status']);
        if (array_key_exists('glb_url', $data)) {
            $columns['glb_url'] = $data['glb_url'] !== '' ? $data['glb_url'] : null;
        }
        if (array_key_exists('dxf_url', $data)) {
            $columns['dxf_url'] = $data['dxf_url'] !== '' ? $data['dxf_url'] : null;
        }
        if (array_key_exists('model_id', $data)) {
            $modelId = $data['model_id'];
            $columns['template_id'] = ($modelId === null || $modelId === '' || $modelId === 0) ? null : $modelId;
        }
        $this->configurations->update($id, $columns);
        return ['success' => true, 'message' => 'Configuration mise à jour avec succès', 'configuration' => $this->configurations->findWithOrderId($id)];
    }

    private function create(array $data, array $configData, bool $isAdmin, ?int $customerId, Session $session): array
    {
        $status = ($isAdmin && isset($data['status'])) ? $data['status'] : 'en_attente_validation';
        $id = $this->configurations->create(
            $customerId === null ? null : (string) $customerId,
            isset($data['model_id']) ? (int) $data['model_id'] : null,
            json_encode($configData, JSON_THROW_ON_ERROR),
            (float) $data['price'],
            $data['glb_url'] ?? null,
            $data['prompt'],
            self::sessionIdOrNull(),
            $status,
        );
        if (isset($data['dxf_url']) && $data['dxf_url'] !== '') {
            $this->configurations->update($id, ['dxf_url' => $data['dxf_url']]);
        }
        $configuration = $this->configurations->findWithOrderId($id);
        $this->notifyAdmin((array) $configuration, $customerId, $isAdmin, $session);
        return ['success' => true, 'message' => 'Configuration sauvegardée avec succès', 'configuration' => $configuration];
    }

    private function notifyAdmin(array $configuration, ?int $customerId, bool $isAdmin, Session $session): void
    {
        $customer = $customerId === null ? null : $this->customers->findFullById($customerId);
        $customer ??= [
            'first_name' => $isAdmin ? 'Admin' : 'Visiteur',
            'last_name' => $session->string('admin_email') ?? 'Système',
            'email' => $session->string('admin_email') ?? 'noreply@archimeuble.com',
            'phone' => '',
        ];
        try {
            $this->mail->sendNewConfigurationNotification($configuration, $customer);
        } catch (\Throwable $notificationFailure) {
            error_log('Failed to send admin notification (create): ' . $notificationFailure->getMessage());
        }
    }

    private static function sessionIdOrNull(): ?string
    {
        $id = session_id();
        return is_string($id) && $id !== '' ? $id : null;
    }

    private static function configData(array $data): array
    {
        $configData = $data['config_data'] ?? [];
        if (isset($data['name'])) {
            $configData['name'] = $data['name'];
        }
        if (isset($data['thumbnail_url'])) {
            $configData['thumbnail_url'] = $data['thumbnail_url'];
        }
        return $configData;
    }
}
