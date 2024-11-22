<?php

# tests/Feature/ApexClientTest.php
namespace Antogkou\LaravelSalesforce\Tests\Feature;


use App\Exceptions\SalesforceException;
use App\Facades\Salesforce;
use App\Http\Integrations\ApexClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
  $user = User::factory()->create();

  auth()->loginUsingId($user->id);
  $this->apexClient = new ApexClient('test@example.com');

  Config::set('salesforce.app_uuid', 'test-uuid');
  Config::set('salesforce.app_key', 'test-key');
  Config::set('salesforce.apex_uri', 'https://salesforce.com');
  Config::set('salesforce.token_uri', 'https://salesforce.com/token');
  Config::set('salesforce.client_id', 'test-client-id');
  Config::set('salesforce.client_secret', 'test-client-secret');
  Config::set('salesforce.username', 'test-username');
  Config::set('salesforce.password', 'test-password');
  Config::set('salesforce.security_token', 'test-security');
});

test('get request is sent correctly', function () {
  Http::fake([
    'salesforce.com/*' => Http::response(['data' => 'success'], 200),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  $response = $this->apexClient->get('test/endpoint');

  expect($response['data'])->toBe('success');

  Http::assertSent(function (Request $request) {
    return $request->url() == 'https://salesforce.com/test/endpoint' &&
      $request->hasHeader('Authorization', 'Bearer fake-token') &&
      $request->hasHeader('x-app-uuid', 'test-uuid') &&
      $request->hasHeader('x-api-key', 'test-key') &&
      $request->hasHeader('x-user-email', 'test@example.com');
  });
});

test('post request is sent correctly', function () {
  Http::fake([
    'salesforce.com/*' => Http::response(['data' => 'success'], 200),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  $response = $this->apexClient->post('test/endpoint', ['key' => 'value']);

  expect($response['data'])->toBe('success');

  Http::assertSent(function (Request $request) {
    return $request->url() == 'https://salesforce.com/test/endpoint' &&
      $request->method() == 'POST' &&
      $request['key'] == 'value';
  });
});

test('put request is sent correctly', function () {
  Http::fake([
    'salesforce.com/*' => Http::response(['data' => 'success'], 200),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  $response = $this->apexClient->put('test/endpoint', ['key' => 'value']);

  expect($response['data'])->toBe('success');

  Http::assertSent(function (Request $request) {
    return $request->url() == 'https://salesforce.com/test/endpoint' &&
      $request->method() == 'PUT' &&
      $request['key'] == 'value';
  });
});

test('patch request is sent correctly', function () {
  Http::fake([
    'salesforce.com/*' => Http::response(['data' => 'success'], 200),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  $response = $this->apexClient->patch('test/endpoint', ['key' => 'value']);

  expect($response['data'])->toBe('success');

  Http::assertSent(function (Request $request) {
    return $request->url() == 'https://salesforce.com/test/endpoint' &&
      $request->method() == 'PATCH' &&
      $request['key'] == 'value';
  });
});

test('delete request is sent correctly', function () {
  Http::fake([
    'salesforce.com/*' => Http::response(['data' => 'success'], 200),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  $response = $this->apexClient->delete('test/endpoint');

  expect($response['data'])->toBe('success');

  Http::assertSent(function (Request $request) {
    return $request->url() == 'https://salesforce.com/test/endpoint' &&
      $request->method() == 'DELETE';
  });
});

test('unauthorized response triggers token refresh', function () {
  Http::fake([
    'salesforce.com/test/endpoint' => Http::sequence()
      ->push(['error' => 'Unauthorized'], 401)
      ->push(['data' => 'success'], 200),
    'salesforce.com/token' => Http::response(['access_token' => 'new-token'], 200),
  ]);

  Cache::shouldReceive('remember')->andReturn('old-token', 'new-token');
  Cache::shouldReceive('forget')->once()->with('salesforceToken');

  $response = $this->apexClient->get('test/endpoint');

  expect($response['data'])->toBe('success');
  Http::assertSentCount(2); // Initial request, token refresh, retry
});

test('failed response throws SalesforceException', function () {
  Http::fake([
    'salesforce.com/*' => Http::response(['error' => 'Bad Request'], 400),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  Log::shouldReceive('error')->once();

  $this->expectException(SalesforceException::class);
  $this->apexClient->get('test/endpoint');
});

test('token retrieval failure throws exception', function () {
  Http::fake([
    'salesforce.com/token' => Http::response(['error' => 'Invalid credentials'], 401),
  ]);

  Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
    return $callback();
  });

  Log::shouldReceive('error')->once();

  $this->expectException(Exception::class);
  $this->apexClient->get('test/endpoint');
});

test('query parameters are handled correctly', function () {
  Http::fake([
    'salesforce.com/test/endpoint?existing=param&new=value' => Http::response(['data' => 'success'], 200),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  $response = $this->apexClient->get('test/endpoint?existing=param', ['new' => 'value']);

  expect($response['data'])->toBe('success');

  Http::assertSent(function (Request $request) {
    return $request->url() == 'https://salesforce.com/test/endpoint?existing=param&new=value';
  });
});

test('setEmail method updates user email', function () {
  Http::fake([
    'salesforce.com/*' => Http::response(['data' => 'success'], 200),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  $this->apexClient->setEmail('new@example.com');
  $this->apexClient->get('test/endpoint');

  Http::assertSent(function (Request $request) {
    return $request->hasHeader('x-user-email', 'new@example.com');
  });
});

test('sendRequest handles exceptions correctly', function () {
  Http::fake([
    'salesforce.com/*' => Http::response(['error' => 'Bad Request'], 400),
  ]);

  Cache::shouldReceive('remember')->andReturn('fake-token');

  Log::shouldReceive('error')->once();

  $this->expectException(Exception::class);
  $this->apexClient->get('test/endpoint');
});

test('options return empty array when no certificates are configured', function () {
  Config::set('salesforce.certificate', null);
  Config::set('salesforce.certificate_key', null);

  $reflector = new ReflectionClass(ApexClient::class);
  $method = $reflector->getMethod('options');
  $method->setAccessible(true);

  $result = $method->invoke(new ApexClient);
  expect($result)->toBe([]);
});

test('options return curl options when certificates are configured', function () {
  Config::set('salesforce.certificate', 'cert.pem');
  Config::set('salesforce.certificate_key', 'key.pem');
  Config::set('app.debug', true);

  $reflector = new ReflectionClass(ApexClient::class);
  $method = $reflector->getMethod('options');
  $method->setAccessible(true);

  $result = $method->invoke(new ApexClient);
  expect($result)->toBe([
    'curl' => [
      CURLOPT_SSLCERT => storage_path('certificates') . DIRECTORY_SEPARATOR . 'cert.pem',
      CURLOPT_SSLKEY => storage_path('certificates') . DIRECTORY_SEPARATOR . 'key.pem',
      CURLOPT_VERBOSE => true,
    ],
  ]);
});

test('non-SalesforceException is logged and rethrown', function () {
  Config::set('salesforce.apex_uri', 'https://example.salesforce.com');
  Cache::shouldReceive('remember')->andReturn('fake-token');

  // Mock the Http facade to throw a generic exception
  Http::fake([
    '*' => function () {
      throw new Exception('Generic error');
    },
  ]);

  // Mock the Log facade to expect an error log
  Log::shouldReceive('error')
    ->once()
    ->withArgs(function ($message, $context) {
      return $message === 'Salesforce API Request Failed' &&
        $context['method'] === 'get' &&
        $context['url'] === 'test/endpoint' &&
        $context['error'] === 'Generic error';
    });

  // Expect the exception to be rethrown
  $this->expectException(Exception::class);
  $this->expectExceptionMessage('Generic error');

  // Attempt to make a request
  $this->apexClient->get('test/endpoint');
});

test('baseUrl adds port 8443 when conditions are met', function () {
  // Set up the conditions
  Config::set('salesforce.apex_uri', 'https://example.salesforce.com');
  Config::set('salesforce.certificate', 'cert.pem');
  Config::set('salesforce.certificate_key', 'key.pem');

  // Create an instance of ApexClient
  $apexClient = new ApexClient;

  // Use reflection to access the private baseUrl method
  $reflector = new ReflectionClass(ApexClient::class);
  $baseUrlMethod = $reflector->getMethod('baseUrl');
  $baseUrlMethod->setAccessible(true);

  // Call the baseUrl method
  $result = $baseUrlMethod->invoke($apexClient);

  // Assert that the URL has been modified to include the port
  expect($result)->toBe('https://example.salesforce.com:8443');
});

test('baseUrl does not add port 8443 when apex_uri already contains it', function () {
  // Set up the conditions
  Config::set('salesforce.apex_uri', 'https://example.salesforce.com:8443');
  Config::set('salesforce.certificate', 'cert.pem');
  Config::set('salesforce.certificate_key', 'key.pem');

  // Create an instance of ApexClient
  $apexClient = new ApexClient;

  // Use reflection to access the private baseUrl method
  $reflector = new ReflectionClass(ApexClient::class);
  $baseUrlMethod = $reflector->getMethod('baseUrl');
  $baseUrlMethod->setAccessible(true);

  // Call the baseUrl method
  $result = $baseUrlMethod->invoke($apexClient);

  // Assert that the URL remains unchanged
  expect($result)->toBe('https://example.salesforce.com:8443');
});

test('baseUrl does not add port 8443 when certificates are not configured', function () {
  // Set up the conditions
  Config::set('salesforce.apex_uri', 'https://example.salesforce.com');
  Config::set('salesforce.certificate', null);
  Config::set('salesforce.certificate_key', null);

  // Create an instance of ApexClient
  $apexClient = new ApexClient;

  // Use reflection to access the private baseUrl method
  $reflector = new ReflectionClass(ApexClient::class);
  $baseUrlMethod = $reflector->getMethod('baseUrl');
  $baseUrlMethod->setAccessible(true);

  // Call the baseUrl method
  $result = $baseUrlMethod->invoke($apexClient);

  // Assert that the URL remains unchanged
  expect($result)->toBe('https://example.salesforce.com');
});

test('throws exception when token cannot be obtained', function () {
  Http::fake([
    config('salesforce.token_uri') => Http::response(['error' => 'invalid_grant'], 400),
  ]);

  Cache::shouldReceive('remember')
    ->once()
    ->andReturnUsing(function ($key, $ttl, $callback) {
      return $callback();
    });

  Salesforce::get('accounts/1234');
})->throws(SalesforceException::class, 'Failed to refresh token: {"error":"invalid_grant"}');

test('throws exception when invalid token is received', function () {
  Http::fake([
    config('salesforce.token_uri') => Http::response(['access_token' => null], 200),
  ]);

  Cache::shouldReceive('remember')
    ->once()
    ->andReturnUsing(function ($key, $ttl, $callback) {
      return $callback();
    });

  Salesforce::setEmail('a@p.com');
  Salesforce::get('accounts/1234');
})->throws(SalesforceException::class, 'Invalid token received from Salesforce');

test('caches valid token', function () {
  Http::fake([
    config('salesforce.token_uri') => Http::response(['access_token' => 'valid_token'], 200),
    'salesforce.com/accounts/1234' => Http::response(['id' => '1234', 'name' => 'Test Account'], 200),
  ]);

  Cache::shouldReceive('remember')
    ->once()
    ->andReturnUsing(function ($key, $ttl, $callback) {
      $token = $callback();
      expect($token)->toBe('valid_token');

      return $token;
    });

  $response = Salesforce::get('accounts/1234');
  expect($response->json())->toBe(['id' => '1234', 'name' => 'Test Account']);
});
