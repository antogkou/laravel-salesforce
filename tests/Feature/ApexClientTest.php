<?php

declare(strict_types=1);

use Antogkou\LaravelSalesforce\ApexClient;
use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('can get token', closure: function () {
    Http::fake([
        'https://test.salesforce.com/services/oauth2/token' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://test.salesforce.com/services/apexrest/test' => Http::response([
            'data' => 'success',
        ]),
    ]);
    $response = app(ApexClient::class)->setEmail('test@test.com')->get('/test');

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

    app(ApexClient::class)->setEmail('test@test.com')->get('/test');
})->throws(SalesforceException::class, 'error');
