<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class ConfigurationDxfPanel
{
    public static function html(array $accessoriesList, string $downloadUrl): string
    {
        $rows = $accessoriesList !== []
            ? implode('', array_map(static fn(string $acc): string => "<tr>
                                <td style='padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); color: #FFFFFF; font-size: 13px;'>
                                    <span style='color: #8B7355; margin-right: 10px;'>→</span> {$acc}
                                </td>
                            </tr>", $accessoriesList))
            : "<tr><td style='color: #A8A7A3; font-size: 13px; text-align: center;'>Aucun aménagement spécifique (caisson vide)</td></tr>";
        return "
            <div style='background-color: #1A1917; padding: 32px; border-radius: 4px; margin-bottom: 24px;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <h4 style='margin: 0 0 8px 0; color: #FFFFFF; font-size: 16px; font-weight: 600;'>PLAN DE FABRICATION 2D</h4>
                    <div style='height: 2px; width: 40px; margin: 0 auto 16px auto; background-color: #8B7355;'></div>
                    <p style='color: #A8A7A3; font-size: 13px; line-height: 1.5;'>Détails des aménagements par compartiment pour la mise en production.</p>
                </div>

                <div style='background-color: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 20px; margin-bottom: 24px;'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        {$rows}
                    </table>
                </div>

                <div style='text-align: center;'>
                    <a href='{$downloadUrl}' style='display: inline-block; border: 1px solid #8B7355; color: #8B7355; padding: 12px 24px; text-decoration: none; font-weight: 600; font-size: 12px; border-radius: 2px; text-transform: uppercase; letter-spacing: 0.1em;'>
                        Télécharger le fichier .DXF
                    </a>
                </div>
            </div>
        ";
    }
}
