<?php

declare(strict_types=1);

namespace App\Domain\Email;

use App\Domain\Model\ModelRepository;
use Throwable;

final class ItemDisplayNameResolver
{
    public function __construct(private readonly ModelRepository $models) {}

    public function resolve(array $item): string
    {
        $modelName = $this->modelNameFromPrompt((string) ($item['prompt'] ?? ''));
        if ($modelName !== null) {
            return $modelName;
        }
        if (isset($item['config_data'])) {
            $config = is_string($item['config_data']) ? json_decode($item['config_data'], true) : $item['config_data'];
            if (is_array($config) && isset($config['name']) && $config['name'] !== '') {
                return (string) $config['name'];
            }
        }
        return 'Meuble sur mesure';
    }

    private function modelNameFromPrompt(string $prompt): ?string
    {
        try {
            foreach ($this->models->namesByPromptPrefix() as $model) {
                if (substr((string) $model['prompt'], 0, 2) === substr($prompt, 0, 2)) {
                    return $model['name'];
                }
            }
        } catch (Throwable $e) {
            error_log('EmailService: Error fetching model name: ' . $e->getMessage());
        }
        return null;
    }
}
