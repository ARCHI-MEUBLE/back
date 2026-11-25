<?php

declare(strict_types=1);

namespace App\Console;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\System\PersonalDataRetention;
use DateTimeImmutable;
use Throwable;

final class PurgePersonalDataCommand
{
    public function __construct(private readonly Settings $settings) {}

    public function run(bool $dryRun): int
    {
        try {
            $retention = new PersonalDataRetention(
                Connection::fromUrl($this->settings->databaseUrl),
                $this->settings->paths->uploadsDir . '/quote-requests',
            );
            $counts = $retention->purge(new DateTimeImmutable(), $dryRun);
        } catch (Throwable $e) {
            fwrite(STDERR, date('[Y-m-d H:i:s] ') . 'Purge des données personnelles impossible : ' . $e->getMessage() . "\n");
            return 1;
        }
        $summary = implode(', ', array_map(static fn(string $k, int $n): string => "{$k}={$n}", array_keys($counts), $counts));
        echo date('[Y-m-d H:i:s] ') . ($dryRun ? 'Simulation, à supprimer : ' : 'Supprimés : ') . $summary . "\n";
        return 0;
    }
}
