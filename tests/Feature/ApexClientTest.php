<?php

// tests/Feature/ApexClientTest.php

use Antogkou\LaravelSalesforce\ApexClient;
use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->client = app(ApexClient::class)->setEmail('test@test.com');
});

it('can get token', function () {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://test.salesforce.com/services/apexrest/test' => Http::response([
            'data' => 'success',
        ]),
    ]);

    $response = $this->client->get('/test');

    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer test-token') &&
            $request->hasHeader('x-app-uuid', 'test-uuid') &&
            $request->hasHeader('x-api-key', 'test-key');
    });

    expect($response->json())->toBe(['data' => 'success']);
});

it('throws exception on error', function () {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://test.salesforce.com/services/apexrest/test' => Http::response([
            'message' => 'error',
        ], 400),
    ]);

    $this->client->get('/test');
})->throws(SalesforceException::class, 'error');
