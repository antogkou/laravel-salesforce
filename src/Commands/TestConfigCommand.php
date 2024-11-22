<?php

declare(strict_types=1);

namespace Antogkou\LaravelSalesforce\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class TestConfigCommand extends Command
{
    protected $signature = 'salesforce:test-config';

    protected $description = 'Test Salesforce config loading';

    public function handle(): int
    {
        $this->info('Testing Salesforce config loading...');

        // Test config access
        $config = config('salesforce');

        if (! is_array($config)) {
            $this->error('Config not found or invalid!');

            return 1;
        }

        $this->info('Config loaded successfully:');

        /** @var Collection<int, array{0: string, 1: string}> */
        $tableRows = collect($config)
            ->map(function ($value, string $key): array {
                return [
                    $key,
                    is_string($value) ? $value : var_export($value, true),
                ];
            });

        $this->table(
            ['Key', 'Value'],
            $tableRows->toArray()
        );

        return 0;
    }
}
