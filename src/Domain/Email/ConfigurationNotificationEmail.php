<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class ConfigurationNotificationEmail
{
    public static function subject(array $config, array $customer): string
    {
        $configName = $config['name'] ?? "Configuration #{$config['id']}";
        return "Nouveau projet client : {$configName} - {$customer['first_name']} {$customer['last_name']}";
    }

    public static function html(array $config, array $customer, string $frontendUrl, string $backendUrl): string
    {
        $price = $config['price'] ?? 0;
        $priceFormatted = number_format((float) $price, 2, ',', ' ') . ' €';
        $type = self::furnitureType($config);
        $viewUrl = "{$frontendUrl}/configurator/{$type}?mode=view&configId={$config['id']}";
        $dimensionsHtml = '';
        $detailsHtml = '';
        $multiColorHtml = '';
        $accessoriesHtml = '';
        $accessoriesList = [];
        if (isset($config['config_string'])) {
            $data = json_decode((string) $config['config_string'], true);
            if (is_array($data)) {
                $dimensionsHtml = ConfigurationDetailSections::dimensions($data['dimensions'] ?? []);
                $detailsHtml = ConfigurationDetailSections::materialsAndDoors($data['styling'] ?? [], $data['features'] ?? []);
                if ((bool) ($data['useMultiColor'] ?? false) && isset($data['componentColors'])) {
                    $multiColorHtml = ConfigurationDetailSections::multiColor($data['componentColors']);
                }
                if (isset($data['advancedZones'])) {
                    $accessoriesList = AccessoryExtractor::extract($data['advancedZones']);
                    $accessoriesHtml = ConfigurationDetailSections::accessories($accessoriesList);
                }
            }
        }
        $promptHtml = isset($config['prompt']) ? self::promptBlock((string) $config['prompt']) : '';
        $dxfUrl = $config['dxf_url'] ?? null;
        $dxfDownloadUrl = $dxfUrl !== null && $dxfUrl !== '' ? "{$frontendUrl}{$dxfUrl}" : "{$backendUrl}/api/files/dxf?id={$config['id']}";
        $dxfLinkHtml = ConfigurationDxfPanel::html($accessoriesList, $dxfDownloadUrl);
        $configName = $config['name'] ?? 'Configuration sans nom';
        $phone = $customer['phone'] ?? '';
        $phoneHtml = $phone !== '' ? "<a href='tel:{$phone}' style='color: #1A1917; text-decoration: none;'>{$phone}</a>" : 'Non renseigné';
        return ConfigurationNotificationLayout::render([
            'configName' => $configName, 'customer' => $customer, 'priceFormatted' => $priceFormatted,
            'dimensionsHtml' => $dimensionsHtml, 'detailsHtml' => $detailsHtml, 'multiColorHtml' => $multiColorHtml,
            'accessoriesHtml' => $accessoriesHtml, 'dxfLinkHtml' => $dxfLinkHtml, 'viewUrl' => $viewUrl,
            'promptHtml' => $promptHtml, 'frontendUrl' => $frontendUrl, 'phoneHtml' => $phoneHtml,
        ]);
    }

    private static function furnitureType(array $config): string
    {
        $type = $config['template_id'] ?? null;
        if (!$type && isset($config['prompt'])) {
            preg_match('/^(M[1-5])/', (string) $config['prompt'], $matches);
            return $matches[1] ?? 'M1';
        }
        return $type !== null && $type !== '' && $type !== 0 ? (string) $type : 'M1';
    }

    private static function promptBlock(string $prompt): string
    {
        return "
                <div style='margin-top: 32px; padding: 16px; background-color: #F8F8F8; border-radius: 4px;'>
                    <p style='margin: 0 0 8px 0; color: #A8A7A3; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;'>Code de fabrication (Prompt)</p>
                    <code style='font-family: \"JetBrains Mono\", monospace; font-size: 11px; color: #706F6C; word-break: break-all;'>{$prompt}</code>
                </div>
            ";
    }
}
