<?php

declare(strict_types=1);

namespace App\Domain\Handle;

use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;
use App\Lib\Validation\ValidationException;

final class HandleModelSchemas
{
    public const SHAPES = ['bar', 'flat_bar', 'profile', 'knob', 'cup', 'recessed', 'leather'];
    private const WITHOUT_LENGTH = ['knob', 'recessed'];
    public const FIELDS = [
        'name', 'shape', 'finish_hex', 'length_mm', 'center_distance_mm', 'width_mm', 'projection_mm', 'image_url',
        'price_per_unit', 'active', 'position',
    ];

    public static function model(): Schema
    {
        return Schema::object([
            'name' => Field::string()->required('Le nom est obligatoire')
                ->minLength(1, 'Le nom est obligatoire')
                ->maxLength(80, 'Le nom ne doit pas dépasser 80 caractères'),
            'shape' => Field::string()->required('La forme est obligatoire')
                ->oneOf(self::SHAPES, 'Forme de poignée invalide (barre ronde, barre plate, profilé, bouton, cuvette, encastrée ou lanière cuir)'),
            'finish_hex' => Field::string()->required('La couleur de finition est obligatoire')
                ->pattern('/^#[0-9A-Fa-f]{6}$/', 'Couleur de finition invalide (format #RRGGBB)'),
            'length_mm' => Field::int()->nullable()
                ->min(20, 'La longueur doit être comprise entre 20 et 800 mm')
                ->max(800, 'La longueur doit être comprise entre 20 et 800 mm'),
            'center_distance_mm' => Field::int()->nullable()
                ->min(10, "L'entraxe doit être compris entre 10 et 800 mm")
                ->max(800, "L'entraxe doit être compris entre 10 et 800 mm"),
            'width_mm' => Field::int()->nullable()
                ->min(2, 'La largeur doit être comprise entre 2 et 100 mm')
                ->max(100, 'La largeur doit être comprise entre 2 et 100 mm'),
            'projection_mm' => Field::int()->nullable()
                ->min(0, 'La saillie doit être comprise entre 0 et 100 mm')
                ->max(100, 'La saillie doit être comprise entre 0 et 100 mm'),
            'image_url' => Field::string()->nullable()
                ->maxLength(500, 'Le lien de la photo ne doit pas dépasser 500 caractères')
                ->rule(self::isImageUrl(...), 'Lien de photo invalide (adresse http(s) ou chemin /uploads/)'),
            'price_per_unit' => Field::float()->default(0.0)
                ->min(0, 'Le prix doit être compris entre 0 et 1000 €')
                ->max(1000, 'Le prix doit être compris entre 0 et 1000 €'),
            'active' => Field::bool()->default(true),
            'position' => Field::int()->nullable()->min(0, 'La position doit être positive'),
        ]);
    }

    public static function id(): Schema
    {
        return Schema::object(['id' => Field::int()->required('id requis')->min(1, 'id invalide')]);
    }

    public static function validate(array $input): array
    {
        $data = self::model()->validate($input);
        if (in_array($data['shape'], self::WITHOUT_LENGTH, true)) {
            $data['length_mm'] = null;
            $data['center_distance_mm'] = null;
        } elseif ($data['length_mm'] === null) {
            throw new ValidationException('La longueur est obligatoire pour cette forme de poignée');
        } elseif ($data['center_distance_mm'] !== null && $data['center_distance_mm'] > $data['length_mm']) {
            throw new ValidationException("L'entraxe ne peut pas dépasser la longueur de la poignée");
        }
        return $data;
    }

    private static function isImageUrl(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (str_starts_with($value, '/uploads/')) {
            return !str_contains($value, '..');
        }
        return preg_match('#^https?://\S+$#i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
