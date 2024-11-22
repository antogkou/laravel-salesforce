<?php

namespace Antogkou\LaravelSalesforce\Commands;

use Illuminate\Console\Command;

class TestConfigCommand extends Command
{
    protected $signature = 'salesforce:test-config';

    protected $description = 'Test Salesforce config loading';

    public function handle(): int
    {
        $this->info('Testing Salesforce config loading...');

        // Test config access
        $config = config('salesforce');

        if ($config === null) {
            $this->error('Config not found!');
            return 1;
        }

        $this->info('Config loaded successfully:');
        $this->table(
            ['Key', 'Value'],
            collect($config)
                ->map(fn ($value, $key) => [$key, is_string($value) ? $value : var_export($value, true)])
                ->toArray()
        );

        return 0;
    }
}
