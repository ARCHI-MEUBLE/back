<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class ConfigurationNotificationLayout
{
    public static function render(array $v): string
    {
        $configName = $v['configName'];
        $customer = $v['customer'];
        $priceFormatted = $v['priceFormatted'];
        $dimensionsHtml = $v['dimensionsHtml'];
        $detailsHtml = $v['detailsHtml'];
        $multiColorHtml = $v['multiColorHtml'];
        $accessoriesHtml = $v['accessoriesHtml'];
        $dxfLinkHtml = $v['dxfLinkHtml'];
        $viewUrl = $v['viewUrl'];
        $promptHtml = $v['promptHtml'];
        $frontendUrl = $v['frontendUrl'];
        $phoneHtml = $v['phoneHtml'];
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Nouveau projet ArchiMeuble</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #F5F5F4; color: #1A1917;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #F5F5F4; padding: 40px 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #FFFFFF; border: 1px solid #E8E6E3; box-shadow: 0 4px 24px rgba(0,0,0,0.04);'>
                            <!-- Header -->
                            <tr>
                                <td style='padding: 40px; border-bottom: 1px solid #F0EFEA; text-align: center;'>
                                    <div style='text-transform: uppercase; letter-spacing: 0.3em; font-size: 10px; font-weight: 700; color: #8B7355; margin-bottom: 16px;'>Notification Admin</div>
                                    <h1 style='margin: 0; font-size: 24px; font-weight: 400; font-family: serif;'>Nouveau projet client</h1>
                                </td>
                            </tr>

                            <!-- Client Info -->
                            <tr>
                                <td style='padding: 40px; background-color: #FAFAF9;'>
                                    <h2 style='margin: 0 0 24px 0; font-size: 18px; font-weight: 600;'>{$configName}</h2>
                                    
                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td style='padding-bottom: 12px;'>
                                                <div style='font-size: 11px; text-transform: uppercase; color: #706F6C; margin-bottom: 4px;'>Client</div>
                                                <div style='font-size: 16px; font-weight: 500;'>{$customer['first_name']} {$customer['last_name']}</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding-bottom: 12px;'>
                                                <div style='font-size: 11px; text-transform: uppercase; color: #706F6C; margin-bottom: 4px;'>Email</div>
                                                <div style='font-size: 15px;'><a href='mailto:{$customer['email']}' style='color: #1A1917; text-decoration: underline;'>{$customer['email']}</a></div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div style='font-size: 11px; text-transform: uppercase; color: #706F6C; margin-bottom: 4px;'>Téléphone</div>
                                                <div style='font-size: 15px;'>{$phoneHtml}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Price Banner -->
                            <tr>
                                <td style='padding: 32px 40px; border-top: 1px solid #F0EFEA; border-bottom: 1px solid #F0EFEA;'>
                                    <table width='100%'>
                                        <tr>
                                            <td style='font-size: 16px; font-weight: 600;'>Estimation du projet</td>
                                            <td align='right' style='font-size: 24px; font-weight: 700; color: #8B7355;'>{$priceFormatted}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Specifications -->
                            <tr>
                                <td style='padding: 40px;'>
                                    <h3 style='margin: 0 0 24px 0; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;'>Spécifications techniques</h3>
                                    
                                    {$dimensionsHtml}
                                    {$detailsHtml}
                                    {$multiColorHtml}
                                    {$accessoriesHtml}
                                    
                                    <!-- DXF Download Section -->
                                    {$dxfLinkHtml}

                                    <!-- 3D View Button -->
                                    <div style='margin-top: 32px; text-align: center;'>
                                        <a href='{$viewUrl}' style='display: block; background-color: #1A1917; color: #FFFFFF; padding: 18px; text-decoration: none; font-weight: 600; font-size: 14px; border-radius: 2px; letter-spacing: 0.1em;'>
                                            VOIR LA CONFIGURATION 3D INTERACTIVE
                                        </a>
                                    </div>

                                    {$promptHtml}
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='padding: 32px 40px; background-color: #FAFAF9; border-top: 1px solid #F0EFEA; text-align: center;'>
                                    <p style='margin: 0; font-size: 12px; color: #706F6C; line-height: 1.6;'>
                                        Ce projet est en attente de validation dans votre tableau de bord.<br>
                                        Connectez-vous pour envoyer le lien de paiement au client.
                                    </p>
                                    <div style='margin-top: 24px;'>
                                        <img src='{$frontendUrl}/logo.png' alt='ArchiMeuble' height='24' style='opacity: 0.5;'>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        
                        <p style='margin-top: 24px; font-size: 11px; color: #A8A7A3; text-align: center;'>
                            © " . date('Y') . ' ArchiMeuble — Manufacture de mobilier sur mesure à Lille.
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }
}
