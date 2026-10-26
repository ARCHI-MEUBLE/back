<?php

declare(strict_types=1);

namespace App\Domain\Email;

use App\Lib\Html;

final class ConfigurationRequestEmail
{
    private const LABEL_STYLE = 'margin: 0 0 8px 0; color: #A8A7A3; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;';

    public static function subject(string $name): string
    {
        return "Nouvelle demande de projet - {$name}";
    }

    public static function html(array $request, array $share, string $link): string
    {
        $year = date('Y');
        $label = self::LABEL_STYLE;
        $name = Html::e($request['name']);
        $phone = Html::e($request['phone']);
        $email = Html::e($request['email']);
        $message = Html::e($request['message']);
        $url = Html::e($link);
        $prompt = Html::e($share['prompt']);
        $phoneHtml = $phone !== '' ? "<a href='tel:{$phone}' style='color: #1A1917;'>{$phone}</a>" : 'Téléphone non renseigné';
        $emailHtml = $email !== '' ? "<a href='mailto:{$email}' style='color: #1A1917;'>{$email}</a>" : 'E-mail non renseigné';
        $messageHtml = $message !== '' ? $message : 'Aucun message.';
        $price = is_float($request['price']) ? number_format($request['price'], 0, ',', ' ') . ' €' : 'Non communiqué';
        $specs = self::specifications(Html::escapeAll($share['config_data']));
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #1A1917; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
                .header { text-align: center; margin-bottom: 40px; }
                .logo { font-size: 24px; font-weight: bold; text-decoration: none; color: #1A1917; }
                .content { background-color: #FFFFFF; padding: 40px; border: 1px solid #E8E6E3; }
                .button { display: inline-block; padding: 16px 32px; background-color: #1A1917; color: #FFFFFF !important; text-decoration: none; font-weight: 600; }
                .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #706F6C; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <span class='logo'>ArchiMeuble</span>
                </div>
                <div class='content'>
                    <h2 style='margin-top: 0;'>Nouvelle demande de projet</h2>
                    <p style='{$label}'>Contact</p>
                    <p style='margin: 0 0 24px 0;'>{$name}<br>{$phoneHtml}<br>{$emailHtml}</p>
                    <p style='{$label}'>Message</p>
                    <p style='margin: 0 0 24px 0; white-space: pre-wrap;'>{$messageHtml}</p>
                    <p style='{$label}'>Prix affiché au client</p>
                    <p style='margin: 0 0 32px 0; font-size: 20px; font-weight: 700;'>{$price}</p>
                    {$specs}
                    <div style='text-align: center; margin: 32px 0 16px 0;'>
                        <a href='{$url}' class='button'>Ouvrir la configuration</a>
                    </div>
                    <p style='margin: 0; text-align: center; font-size: 12px; word-break: break-all;'><a href='{$url}' style='color: #706F6C;'>{$url}</a></p>
                    <p style='{$label} margin-top: 32px;'>Code de fabrication</p>
                    <code style='font-family: monospace; font-size: 11px; color: #706F6C; word-break: break-all;'>{$prompt}</code>
                </div>
                <div class='footer'>
                    <p>Ces coordonnées ne sont pas enregistrées sur le site : cet e-mail en est la seule copie.</p>
                    <p>&copy; {$year} ArchiMeuble</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function specifications(array $data): string
    {
        $dimensions = is_array($data['dimensions'] ?? null) ? $data['dimensions'] : [];
        $styling = is_array($data['styling'] ?? null) ? $data['styling'] : [];
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        $colors = (bool) ($data['useMultiColor'] ?? false) && is_array($data['componentColors'] ?? null)
            ? ConfigurationDetailSections::multiColor($data['componentColors'])
            : '';
        return ConfigurationDetailSections::dimensions($dimensions)
            . ConfigurationDetailSections::materialsAndDoors($styling, $features)
            . $colors
            . ConfigurationDetailSections::accessories(AccessoryExtractor::extract($data['advancedZones'] ?? null));
    }
}
