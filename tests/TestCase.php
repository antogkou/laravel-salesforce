<?php

declare(strict_types=1);

namespace Antogkou\LaravelSalesforce\Tests;

use Antogkou\LaravelSalesforce\SalesforceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SalesforceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('salesforce', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'username' => 'test@example.com',
            'password' => 'password',
            'security_token' => 'token',
            'token_uri' => 'https://test.salesforce.com/services/oauth2/token',
            'apex_uri' => 'https://test.salesforce.com/services/apexrest',
            'app_uuid' => 'test-uuid',
            'app_key' => 'test-key',
        ]);
    }
}
