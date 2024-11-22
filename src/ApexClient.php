<?php

declare(strict_types=1);

namespace Antogkou\LaravelSalesforce;

use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ApexClient
{
    private const TOKEN_CACHE_KEY = 'salesforceToken';

    private const TOKEN_CACHE_TTL = 28800;

    private const ALLOWED_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    public function __construct(
        private ?string $userEmail = null
    ) {
    }

    /**
     * Magic method to handle HTTP requests dynamically
     *
     * @throws SalesforceException
     */
    public function __call(string $method, array $arguments): Response
    {
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new SalesforceException("Method {$method} not supported");
        }

        $url = $arguments[0] ?? '';
        $data = $arguments[1] ?? [];
        $headers = $arguments[2] ?? [];
        $query = $method === 'get' ? $data : [];

        return $this->sendRequest(
            method: $method,
            url: $url,
            query: $query,
            data: $method !== 'get' ? $data : [],
            additionalHeaders: $headers
        );
    }

    /**
     * @throws SalesforceException
     */
    private function sendRequest(
        string $method,
        string $url,
        array $query = [],
        array $data = [],
        array $additionalHeaders = [],
    ): Response {
        $request = $this->buildRequest($additionalHeaders);
        $fullUrl = $this->buildUrl($url, $query);

        try {
            $response = $this->executeRequest($request, $method, $fullUrl, $data);

            if ($response->unauthorized()) {
                cache()->forget(self::TOKEN_CACHE_KEY);
                $response = $this->executeRequest(
                    $this->buildRequest($additionalHeaders),
                    $method,
                    $fullUrl,
                    $data
                );
            }

            $this->handleFailedResponse($response, $method, $fullUrl, $data);

            return $response;
        } catch (Exception $e) {
            $this->logError($e, $method, $url, $data);
            throw $e;
        }
    }

    /**
     * @throws SalesforceException
     */
    private function buildRequest(array $additionalHeaders = []): PendingRequest
    {
        return Http::baseUrl($this->getBaseUrl())
            ->withToken($this->getToken())
            ->withHeaders($this->buildHeaders($additionalHeaders))
            ->withOptions($this->getRequestOptions());
    }

    /**
     * @throws SalesforceException
     */
    private function getBaseUrl(): string
    {
        $apexUri = config('salesforce.apex_uri');
        if (!is_string($apexUri)) {
            throw new SalesforceException('Invalid apex_uri configuration');
        }

        if (!Str::contains($apexUri, '.com:8443') && Arr::exists($this->getRequestOptions(), 'curl')) {
            $apexUri = Str::replaceFirst('.com', '.com:8443', $apexUri);
        }

        return rtrim($apexUri, '/');
    }

    private function getRequestOptions(): array
    {
        if (!config('salesforce.certificate') || !config('salesforce.certificate_key')) {
            return [];
        }

        return [
            'curl' => [
                CURLOPT_SSLCERT => storage_path('certificates/'.config('salesforce.certificate')),
                CURLOPT_SSLKEY => storage_path('certificates/'.config('salesforce.certificate_key')),
                CURLOPT_VERBOSE => config('app.debug'),
            ],
        ];
    }

    /**
     * @throws SalesforceException
     */
    private function getToken(): string
    {
        try {
            return cache()->remember(
                self::TOKEN_CACHE_KEY,
                self::TOKEN_CACHE_TTL,
                fn(): string => $this->refreshToken()
            );
        } catch (Exception $e) {
            Log::error('Failed to obtain Salesforce token', ['error' => $e->getMessage()]);
            throw new SalesforceException($e->getMessage(), 500, $e);
        }
    }

    /**
     * @throws SalesforceException|ConnectionException
     */
    private function refreshToken(): string
    {
        $tokenUri = config('salesforce.token_uri');
        if (!is_string($tokenUri)) {
            throw new SalesforceException('Invalid token_uri configuration');
        }

        $response = Http::asForm()->post($tokenUri, [
            'grant_type' => 'password',
            'client_id' => config('salesforce.client_id'),
            'client_secret' => config('salesforce.client_secret'),
            'username' => config('salesforce.username'),
            'password' => config('salesforce.password').config('salesforce.security_token'),
        ]);

        if ($response->successful()) {
            $token = $response->json('access_token');
            if (is_string($token) && $token !== '' && $token !== '0') {
                return $token;
            }
        }

        throw new SalesforceException(
            $response->successful() ? 'Invalid token received' : 'Failed to refresh token: '.$response->body(),
            $response->status() ?: 500,
            null,
            ['response' => $response->json()]
        );
    }

    private function buildHeaders(array $additionalHeaders): array
    {
        return array_merge([
            'x-app-uuid' => config('salesforce.app_uuid'),
            'x-api-key' => config('salesforce.app_key'),
            'x-user-email' => $this->userEmail ?? (Auth::user()?->email ?? ''),
        ], $additionalHeaders);
    }

    /**
     * @throws SalesforceException
     */
    private function buildUrl(string $url, array $query = []): string
    {
        $fullUrl = Str::startsWith($url, ['http://', 'https://'])
            ? $url
            : $this->getBaseUrl().'/'.ltrim($url, '/');

        $request = request()->create($fullUrl);

        // Merge existing query parameters with new ones
        $mergedQuery = array_merge(
            $request->query->all(),
            $query
        );

        if ($mergedQuery === []) {
            return $fullUrl;
        }

        return $request->getSchemeAndHttpHost()
            .$request->getBasePath()
            .$request->getPathInfo()
            .($mergedQuery !== [] ? '?'.http_build_query($mergedQuery) : '');
    }

    private function executeRequest(PendingRequest $request, string $method, string $url, array $data): Response
    {
        return $method === 'get' ? $request->get($url) : $request->$method($url, $data);
    }

    /**
     * @throws SalesforceException
     */
    private function handleFailedResponse(Response $response, string $method, string $url, array $data): void
    {
        if ($response->failed()) {
            $errorBody = $response->json() ?? $response->body();
            $routeInfo = [
                'uri' => request()->path(),
                'name' => request()->route()?->getName(),
                'action' => request()->route()?->getActionName(),
            ];

            Log::error('Salesforce API Error', [
                'method' => $method,
                'url' => $url,
                'data' => $data,
                'status' => $response->status(),
                'response' => $errorBody,
                'route' => $routeInfo,
            ]);

            throw new SalesforceException(
                $errorBody['message'] ?? 'Unknown Salesforce API error',
                $response->status(),
                null,
                [
                    'method' => $method, 'url' => $url, 'data' => $data, 'errorBody' => $errorBody,
                    'routeInfo' => $routeInfo,
                ]
            );
        }
    }

    private function logError(Exception $e, string $method, string $url, array $data): void
    {
        if (!$e instanceof SalesforceException) {
            Log::error('Salesforce API Request Failed', [
                'method' => $method,
                'url' => $url,
                'data' => $data,
                'error' => $e->getMessage(),
                'route' => [
                    'uri' => request()->path(),
                    'name' => request()->route()?->getName(),
                    'action' => request()->route()?->getActionName(),
                ],
            ]);
        }
    }

    public function setEmail(string $email): self
    {
        $this->userEmail = $email;

        return $this;
    }
}
