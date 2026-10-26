<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Infrastructure\RateLimit\Throttle;

final class ConfigurationShareService
{
    public const MAX_CONFIG_BYTES = 200000;
    public const NOT_FOUND = 'Ce lien de configuration est introuvable';

    public function __construct(
        private readonly ConfigurationShareRepository $shares,
        private readonly Throttle $throttle,
    ) {}

    public function create(array $input, string $ip): string
    {
        $configuratorId = is_string($input['configurator_id'] ?? null) ? trim($input['configurator_id']) : '';
        if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $configuratorId) !== 1) {
            throw new DomainException('Meuble invalide');
        }
        $prompt = is_string($input['prompt'] ?? null) ? trim($input['prompt']) : '';
        if ($prompt === '' || strlen($prompt) > GenerateRoutes::MAX_PROMPT_LENGTH) {
            throw new DomainException('Code de fabrication invalide');
        }
        $config = $input['config_data'] ?? null;
        if (!is_array($config) || $config === [] || array_is_list($config)) {
            throw new DomainException('Configuration invalide');
        }
        $configJson = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($configJson) > self::MAX_CONFIG_BYTES) {
            throw new DomainException('Configuration trop volumineuse');
        }
        $this->throttle->consume($ip, 'configuration_share_ip', 60, 60);
        return $this->shares->save($configuratorId, $prompt, $configJson);
    }

    public function open(string $code): array
    {
        if (!ShareCode::isValid($code)) {
            throw new NotFoundException(self::NOT_FOUND);
        }
        return $this->shares->open($code) ?? throw new NotFoundException(self::NOT_FOUND);
    }
}
