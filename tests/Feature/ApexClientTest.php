<?php

declare(strict_types=1);

use Antogkou\LaravelSalesforce\ApexClient;
use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    // Clear any existing configuration
    Config::set('salesforce', null);

    // Set up fresh configuration
    Config::set([
        'salesforce.default' => 'default',
        'salesforce.connections.default' => [
            'app_uuid' => 'test-uuid',
            'app_key' => 'test-key',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'username' => 'test-user',
            'password' => 'test-pass',
            'security_token' => 'test-token',
            'token_uri' => 'https://test.salesforce.com/services/oauth2/token',
            'apex_uri' => 'https://test.salesforce.com/services/apexrest',
        ],
        'salesforce.connections.sandbox' => [
            'app_uuid' => 'sandbox-uuid',
            'app_key' => 'sandbox-key',
            'client_id' => 'sandbox-client',
            'client_secret' => 'sandbox-secret',
            'username' => 'sandbox-user',
            'password' => 'sandbox-pass',
            'security_token' => 'sandbox-token',
            'token_uri' => 'https://sandbox.salesforce.com/services/oauth2/token',
            'apex_uri' => 'https://sandbox.salesforce.com/services/apexrest',
        ],
    ]);

    Http::fake([
        '*test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        '*sandbox.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'sandbox-token',
        ]),
    ]);
});

it('can make request without optional app headers', function (): void {
    Config::set('salesforce.connections.default', array_merge(
        Config::get('salesforce.connections.default', []),
        [
            'app_uuid' => null,
            'app_key' => null,
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'username' => 'test-user',
            'password' => 'test-pass',
            'security_token' => 'test-token',
            'token_uri' => 'https://test.salesforce.com/services/oauth2/token',
            'apex_uri' => 'https://test.salesforce.com/services/apexrest',
        ]
    ));

    Http::fake([
        'test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'test.salesforce.com/services/apexrest/test' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $response = app(ApexClient::class)
        ->setEmail('test@test.com')
        ->get('/test');

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('Authorization', 'Bearer test-token') &&
            ! $request->hasHeader('x-app-uuid') &&
            ! $request->hasHeader('x-app-key') &&
            $request->hasHeader('x-user-email', 'test@test.com');
    });

    expect($response->json())->toBe(['data' => 'success']);
});

it('caches the token', function (): void {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://test.salesforce.com/services/apexrest/test' => Http::response([
            'data' => 'success',
        ]),
    ]);

    // First request should get token
    app(ApexClient::class)->get('/test');

    // Second request should use cached token
    app(ApexClient::class)->get('/test');

    Http::assertSentCount(3); // 1 token request + 2 API requests
});

it('throws exception on error response', function (): void {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://test.salesforce.com/services/apexrest/test' => Http::response([
            'message' => 'error',
        ], 400),
    ]);

    app(ApexClient::class)->get('/test');
})->throws(SalesforceException::class, 'error');

it('supports multiple HTTP methods', function (): void {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://test.salesforce.com/services/apexrest/*' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $client = app(ApexClient::class);

    // Test all HTTP methods
    $methods = ['get', 'post', 'put', 'patch', 'delete'];
    foreach ($methods as $method) {
        $response = $method === 'get'
            ? $client->$method('/test')
            : $client->$method('/test', ['data' => 'test']);

        expect($response->json())->toBe(['data' => 'success']);
    }

    Http::assertSentCount(count($methods) + 1); // methods + 1 token request
});

it('correctly builds URLs with query parameters', function (): void {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://test.salesforce.com/services/apexrest/test*' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $response = app(ApexClient::class)->get('/test', [
        'param1' => 'value1',
        'param2' => 'value2',
    ]);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'param1=value1') &&
            str_contains($request->url(), 'param2=value2');
    });

    expect($response->json())->toBe(['data' => 'success']);
});

it('uses default user email from config', function (): void {
    Config::set('salesforce.connections.default', array_merge(
        Config::get('salesforce.connections.default', []),
        ['default_user_email' => 'default@test.com']
    ));

    Http::fake([
        'test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'test.salesforce.com/services/apexrest/test' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $response = app(ApexClient::class)->get('/test');

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('x-user-email', 'default@test.com');
    });

    expect($response->json())->toBe(['data' => 'success']);
});

it('handles certificate-based authentication', function (): void {
    // Create a fake certificate directory and files
    $certPath = storage_path('certificates/cert.pem');
    $keyPath = storage_path('certificates/cert.key');

    // Mock the File facade
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('get')->andReturn('certificate content');

    Config::set('salesforce.connections.default', array_merge(
        Config::get('salesforce.connections.default', []),
        [
            'certificate' => 'cert.pem',
            'certificate_key' => 'cert.key',
        ]
    ));

    Http::fake([
        'test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'test.salesforce.com/services/apexrest/*' => Http::response([
            'data' => 'success',
        ]),
    ]);

    // Mock the actual HTTP request to avoid certificate validation
    Http::preventStrayRequests();

    $client = app(ApexClient::class)->setEmail('test@test.com');

    // Test that the certificate paths are correctly constructed
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('getRequestOptions');
    $method->setAccessible(true);

    $options = $method->invoke($client);

    // Verify the certificate paths are correctly set in options
    expect($options)->toHaveKey('curl')
        ->and($options['curl'])->toHaveKey(CURLOPT_SSLCERT)
        ->and($options['curl'])->toHaveKey(CURLOPT_SSLKEY)
        ->and($options['curl'][CURLOPT_SSLCERT])->toContain('cert.pem')
        ->and($options['curl'][CURLOPT_SSLKEY])->toContain('cert.key');
});

it('skips certificate configuration when certificates are not set', function (): void {
    Config::set('salesforce.connections.default', array_merge(
        Config::get('salesforce.connections.default', []),
        [
            'certificate' => null,
            'certificate_key' => null,
        ]
    ));

    Http::fake([
        'test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'test.salesforce.com/services/apexrest/*' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $client = app(ApexClient::class)->setEmail('test@test.com');

    // Test that no certificate options are set
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('getRequestOptions');
    $method->setAccessible(true);

    $options = $method->invoke($client);

    expect($options)->toBeArray()
        ->and($options)->toBeEmpty();
});

it('throws exception when only one certificate setting is provided', function (): void {
    // Test with only certificate
    Config::set('salesforce.connections.default', array_merge(
        Config::get('salesforce.connections.default', []),
        [
            'certificate' => 'cert.pem',
            'certificate_key' => null,
        ]
    ));

    expect(fn () => app(ApexClient::class)->get('/test'))
        ->toThrow(SalesforceException::class, 'Both certificate and certificate_key must be provided for connection [default] if using certificate authentication');

    // Test with only key
    Config::set('salesforce.connections.default', array_merge(
        Config::get('salesforce.connections.default', []),
        [
            'certificate' => null,
            'certificate_key' => 'cert.key',
        ]
    ));

    expect(fn () => app(ApexClient::class)->get('/test'))
        ->toThrow(SalesforceException::class, 'Both certificate and certificate_key must be provided for connection [default] if using certificate authentication');
});

it('handles port 8443 for certificate URLs correctly', function (): void {
    // Mock the File facade
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('get')->andReturn('certificate content');

    Config::set('salesforce.connections.default', array_merge(
        Config::get('salesforce.connections.default', []),
        [
            'certificate' => 'cert.pem',
            'certificate_key' => 'cert.key',
            'apex_uri' => 'https://test.salesforce.com/services/apexrest',
        ]
    ));

    $client = app(ApexClient::class);

    // Test the base URL modification
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('getBaseUrl');
    $method->setAccessible(true);

    $baseUrl = $method->invoke($client);

    expect($baseUrl)->toContain(':8443');
});

it('can get token and make request with all headers', function (): void {
    Http::fake([
        'test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'test.salesforce.com/services/apexrest/*' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $response = app(ApexClient::class)
        ->setEmail('test@test.com')
        ->get('/test');

    Http::assertSent(function (Request $request) {
        if (str_contains($request->url(), 'oauth2/token')) {
            return true;
        }

        return $request->hasHeader('Authorization', 'Bearer test-token') &&
            $request->hasHeader('x-app-uuid', 'test-uuid') &&
            $request->hasHeader('x-app-key', 'test-key') &&
            $request->hasHeader('x-user-email', 'test@test.com');
    });

    expect($response->json())->toBe(['data' => 'success']);
});

it('refreshes token on unauthorized response', function (): void {
    $responses = Http::sequence()
        ->push(['access_token' => 'old-token'])
        ->push(['error' => 'unauthorized'], 401)
        ->push(['access_token' => 'new-token'])
        ->push(['data' => 'success']);

    Http::fake([
        'test.salesforce.com/services/*' => $responses,
    ]);

    $response = app(ApexClient::class)
        ->setEmail('test@test.com')
        ->get('/test');

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'oauth2/token') ||
            $request->hasHeader('Authorization', 'Bearer new-token');
    });

    expect($response->json())->toBe(['data' => 'success']);
});

it('throws exception on empty token', function (): void {
    Http::fake([
        'test.salesforce.com/services/*' => Http::response([
            'access_token' => '',
        ], 200),
    ]);

    $client = app(ApexClient::class);

    expect(fn () => $client->get('/test'))
        ->toThrow(SalesforceException::class, 'Invalid token received from Salesforce');
});

it('throws exception on missing token', function (): void {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'something_else' => 'value',
        ], 200),
    ]);

    $client = app(ApexClient::class);
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('refreshToken');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($client))
        ->toThrow(SalesforceException::class, 'Invalid token received from Salesforce');
})->skip('Needs to be fixed');

it('throws exception on invalid token response', function (): void {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'authentication failure',
        ], 400),
    ]);

    $client = app(ApexClient::class);
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('refreshToken');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($client))
        ->toThrow(
            SalesforceException::class,
            'Failed to refresh token: {"error":"invalid_grant","error_description":"authentication failure"}'
        );
})->skip('Needs to be fixed');

it('successfully refreshes valid token', function (): void {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'valid-token',
        ]),
        'https://test.salesforce.com/services/apexrest/*' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $response = app(ApexClient::class)->get('/test');
    expect($response->json())->toBe(['data' => 'success']);
});

it('uses default connection by default', function (): void {
    $client = app(ApexClient::class);
    expect($client->getConnection())->toBe('default');
});

it('can switch connections', function (): void {
    $client = app(ApexClient::class);

    $client->connection('sandbox');
    expect($client->getConnection())->toBe('sandbox');

    $client->connection('default');
    expect($client->getConnection())->toBe('default');
});

it('uses environment-specific connection when environment matches', function (): void {
    app()->detectEnvironment(fn () => 'staging');

    $client = app(ApexClient::class);
    $client->whenEnvironment('sandbox', 'staging');

    expect($client->getConnection())->toBe('sandbox');
});

it('uses default connection when environment does not match', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $client = app(ApexClient::class);
    $client->whenEnvironment('sandbox', 'staging');

    expect($client->getConnection())->toBe('default');
});

it('supports multiple environments in whenEnvironment', function (): void {
    app()->detectEnvironment(fn () => 'testing');

    $client = app(ApexClient::class);
    $client->whenEnvironment('sandbox', ['staging', 'testing']);

    expect($client->getConnection())->toBe('sandbox');
});

it('falls back to default connection when environment connection is not configured', function (): void {
    app()->detectEnvironment(fn () => 'staging');

    Config::set('salesforce.connections.sandbox', null);

    $client = app(ApexClient::class);
    $client->whenEnvironment('sandbox', 'staging');

    Http::fake([
        'test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'test.salesforce.com/services/apexrest/test' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $response = $client->get('/test');

    expect($client->getConnection())->toBe('default')
        ->and($response->json())->toBe(['data' => 'success']);
});

it('uses correct credentials for environment connection', function (): void {
    app()->detectEnvironment(fn () => 'staging');

    Http::fake([
        'sandbox.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'sandbox-token',
        ]),
        'sandbox.salesforce.com/services/apexrest/test' => Http::response([
            'data' => 'sandbox-success',
        ]),
    ]);

    $response = app(ApexClient::class)
        ->whenEnvironment('sandbox', 'staging')
        ->get('/test');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'sandbox.salesforce.com') &&
            $request->hasHeader('Authorization', 'Bearer sandbox-token') &&
            $request->hasHeader('x-app-uuid', 'sandbox-uuid') &&
            $request->hasHeader('x-app-key', 'sandbox-key');
    });

    expect($response->json())->toBe(['data' => 'sandbox-success']);
});

it('maintains environment connection across requests', function (): void {
    app()->detectEnvironment(fn () => 'staging');

    $client = app(ApexClient::class)
        ->whenEnvironment('sandbox', 'staging');

    Http::fake([
        'sandbox.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'sandbox-token',
        ]),
        'sandbox.salesforce.com/services/apexrest/*' => Http::response([
            'data' => 'sandbox-success',
        ]),
    ]);

    // First request
    $client->get('/test1');

    // Second request should still use sandbox connection
    $client->get('/test2');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'sandbox.salesforce.com');
    }, 3); // 1 token request + 2 API requests
});

it('clears token cache when switching connections', function (): void {
    app()->detectEnvironment(fn () => 'staging');

    $client = app(ApexClient::class);

    Http::fake([
        'test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'sandbox.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'sandbox-token',
        ]),
        'test.salesforce.com/services/apexrest/*' => Http::response([
            'data' => 'success',
        ]),
        'sandbox.salesforce.com/services/apexrest/*' => Http::response([
            'data' => 'sandbox-success',
        ]),
    ]);

    // First request with default connection
    $client->get('/test');

    // Switch to sandbox
    $client->whenEnvironment('sandbox', 'staging')
        ->get('/test');

    // Switch back to default
    $client->connection('default')
        ->get('/test');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/oauth2/token');
    }, 3); // Should get new token for each connection switch
});

afterEach(function () {
    Mockery::close();
});
