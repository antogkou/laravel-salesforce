<?php

# tests/Feature/ApexClientTest.php
namespace Antogkou\LaravelSalesforce\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use YourVendor\LaravelSalesforce\ApexClient;
use YourVendor\LaravelSalesforce\Exceptions\SalesforceException;
use YourVendor\LaravelSalesforce\SalesforceServiceProvider;

class ApexClientTest extends TestCase
{
protected function getPackageProviders($app): array
{
return [SalesforceServiceProvider::class];
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

public function test_it_can_get_token(): void
{
Http::fake([
'https://test.salesforce.com/services/oauth2/token' => Http::response([
'access_token' => 'test-token',
]),
'https://test.salesforce.com/services/apexrest/test' => Http::response([
'data' => 'success',
]),
]);

$response = $this->app->make(ApexClient::class)->get('/test');

Http::assertSent(function (Request $request) {
return $request->hasHeader('Authorization', 'Bearer test-token') &&
$request->hasHeader('x-app-uuid', 'test-uuid') &&
$request->hasHeader('x-api-key', 'test-key');
});

$this->assertEquals(['data' => 'success'], $response->json());
}

public function test_it_throws_exception_on_error(): void
{
Http::fake([
'https://test.salesforce.com/services/oauth2/token' => Http::response([
'access_token' => 'test-token',
]),
'https://test.salesforce.com/services/apexrest/test' => Http::response([
'message' => 'error',
], 400),
]);

$this->expectException(SalesforceException::class);
$this->expectExceptionMessage('error');

$this->app->make(ApexClient::class)->get('/test');
}
}
