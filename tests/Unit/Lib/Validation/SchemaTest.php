<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Validation;

use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;
use App\Lib\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testCoercesAndAppliesDefaults(): void
    {
        $schema = Schema::object([
            'email' => Field::string()->email()->required(),
            'quantity' => Field::int()->min(1)->default(1),
            'price' => Field::float()->required(),
            'active' => Field::bool()->default(true),
            'notes' => Field::string()->nullable(),
            'tags' => Field::array(),
        ]);

        $output = $schema->validate(['email' => ' a@b.co ', 'price' => '12.50', 'active' => '0', 'notes' => '']);

        self::assertSame(['email' => 'a@b.co', 'quantity' => 1, 'price' => 12.5, 'active' => false, 'notes' => null], $output);
    }

    public function testRequiredMessageIsCustomisable(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email et mot de passe requis');

        Schema::object(['email' => Field::string()->required('Email et mot de passe requis')])->validate([]);
    }

    public function testRulesRejectInvalidValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Valeur non autorisée');

        Schema::object(['status' => Field::string()->oneOf(['a', 'b'])])->validate(['status' => 'c']);
    }

    public function testTypeMismatchIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Le champ quantity est invalide');

        Schema::object(['quantity' => Field::int()])->validate(['quantity' => 'abc']);
    }
}
