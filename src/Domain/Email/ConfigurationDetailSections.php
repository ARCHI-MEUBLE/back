<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class ConfigurationDetailSections
{
    public static function dimensions(array $dim): string
    {
        $w = $dim['width'] ?? 0;
        $h = $dim['height'] ?? 0;
        $d = $dim['depth'] ?? 0;
        return "
                    <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                        <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>📐 Dimensions de l'ouvrage</h4>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Largeur</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$w} mm</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Hauteur</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$h} mm</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px;'>Profondeur</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$d} mm</td>
                            </tr>
                        </table>
                    </div>
                ";
    }

    public static function materialsAndDoors(array $styling, array $features): string
    {
        $material = $styling['materialLabel'] ?? ($styling['materialKey'] ?? 'Standard');
        $finish = $styling['finish'] ?? 'Non spécifiée';
        $color = $styling['colorLabel'] ?? ($styling['color'] ?? 'Standard');
        $socle = $styling['socle'] ?? 'none';
        $socleLabel = $socle === 'metal' ? 'Socle métal noir' : ($socle === 'wood' ? 'Socle bois' : 'Sans socle (pose au sol)');
        $html = "
                    <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                        <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>🎨 Finitions & Matériaux</h4>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Matériau principal</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$material}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Finition</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$finish}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Couleur dominante</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$color}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #706F6C; font-size: 14px;'>Type de socle</td>
                                <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$socleLabel}</td>
                            </tr>
                        </table>
                    </div>
                ";
        $doorType = $features['doorType'] ?? 'none';
        if ($doorType !== 'none') {
            $html .= self::doors($doorType, $features['doorSide'] ?? 'none');
        }
        return $html;
    }

    private static function doors(string $doorType, string $doorSide): string
    {
        $doorTypeLabel = match ($doorType) {
            'sliding' => 'Coulissante', 'hinged' => 'Battante', 'lift' => 'Relevable',
            'double' => 'Double porte', 'single' => 'Porte simple', default => 'Aucune',
        };
        $doorSideLabel = match ($doorSide) {
            'left' => 'Gauche', 'right' => 'Droite', default => 'N/A',
        };
        return "
                        <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                            <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>🚪 Système d'ouverture</h4>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 10px 0; color: #706F6C; font-size: 14px; border-bottom: 1px solid #F0EFEA;'>Type de porte</td>
                                    <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$doorTypeLabel}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px 0; color: #706F6C; font-size: 14px;'>Sens d'ouverture</td>
                                    <td style='padding: 10px 0; color: #1A1917; text-align: right; font-weight: 600;'>{$doorSideLabel}</td>
                                </tr>
                            </table>
                        </div>
                    ";
    }

    public static function multiColor(array $componentColors): string
    {
        if ($componentColors === []) {
            return '';
        }
        $labels = ['structure' => 'Structure', 'drawers' => 'Tiroirs', 'doors' => 'Portes', 'shelves' => 'Étagères', 'back' => 'Fond', 'base' => 'Socle'];
        $html = "
                        <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                            <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>🌈 Détails Multi-couleurs</h4>
                            <table style='width: 100%; border-collapse: collapse;'>
                    ";
        foreach ($componentColors as $key => $val) {
            if (!isset($labels[$key])) {
                continue;
            }
            $cLabel = $val['colorLabel'] ?? ($val['hex'] ?? 'Standard');
            $html .= "
                                <tr>
                                    <td style='padding: 8px 0; color: #706F6C; font-size: 13px; border-bottom: 1px solid #F0EFEA;'>{$labels[$key]}</td>
                                    <td style='padding: 8px 0; color: #1A1917; text-align: right; font-weight: 500;'>{$cLabel}</td>
                                </tr>
                            ";
        }
        return $html . '</table></div>';
    }

    public static function accessories(array $accessoriesList): string
    {
        if ($accessoriesList === []) {
            return '';
        }
        $html = "
                            <div style='background-color: #FAFAF9; padding: 24px; border: 1px solid #E8E6E3; margin-bottom: 24px;'>
                                <h4 style='margin: 0 0 16px 0; color: #1A1917; font-size: 14px; text-transform: uppercase; tracking: 0.1em; font-weight: 700;'>📦 Aménagements intérieurs</h4>
                                <table style='width: 100%; border-collapse: collapse;'>
                        ";
        foreach ($accessoriesList as $acc) {
            $html .= "
                                <tr>
                                    <td style='padding: 8px 0; color: #1A1917; font-size: 13px; border-bottom: 1px solid #F0EFEA;'>
                                        <span style='color: #8B7355; margin-right: 8px;'>•</span> {$acc}
                                    </td>
                                </tr>
                            ";
        }
        return $html . '</table></div>';
    }
}
