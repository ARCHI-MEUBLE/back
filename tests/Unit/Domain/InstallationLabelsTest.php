<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Email\ConfigurationDetailSections;
use App\Domain\Order\OrderConfirmationSections;
use App\Domain\Order\OrderLines;
use PHPUnit\Framework\TestCase;

final class InstallationLabelsTest extends TestCase
{
    private const WITH = ['name' => 'Bibliothèque', 'features' => ['installation' => true]];
    private const WITHOUT = ['name' => 'Bibliothèque', 'features' => ['installation' => false]];

    public function testOrderLinesSayWhenTheInstallationIsInTheFurniturePrice(): void
    {
        $rows = OrderLines::configurationRows([
            ['config_data' => json_encode(self::WITH), 'quantity' => 1, 'unit_price' => 1470],
            ['config_data' => self::WITHOUT, 'quantity' => 2, 'unit_price' => 1380],
            ['config_data' => '{"features":{"installation":"true"}}', 'quantity' => 1, 'unit_price' => 900],
            ['config_data' => null, 'quantity' => 1, 'unit_price' => 900],
        ]);

        self::assertSame(
            ['Bibliothèque · pose et montage compris', 'Bibliothèque', 'Meuble sur mesure', 'Meuble sur mesure'],
            array_column($rows, 'display_name'),
        );
        self::assertSame(2760.0, $rows[1]['total_price']);
    }

    public function testTheConfirmationEmailNamesTheInstallation(): void
    {
        $html = OrderConfirmationSections::configurations([
            ['config_data' => json_encode(self::WITH), 'quantity' => 1, 'unit_price' => 1470],
        ]);

        self::assertStringContainsString('Bibliothèque · pose et montage compris', $html);
    }

    public function testTheWorkshopEmailTellsWhetherToInstall(): void
    {
        self::assertStringContainsString('Comprise', ConfigurationDetailSections::materialsAndDoors([], ['installation' => true]));
        self::assertStringContainsString('Non comprise (livraison seule)', ConfigurationDetailSections::materialsAndDoors([], ['installation' => false]));
        self::assertStringContainsString('Non comprise (livraison seule)', ConfigurationDetailSections::materialsAndDoors([], []));
    }
}
