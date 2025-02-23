<?php

declare(strict_types=1);

namespace Antogkou\LaravelSalesforce;

use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ApexClient
{
    private const TOKEN_CACHE_KEY = 'salesforceToken';

    private const TOKEN_CACHE_TTL = 28800;

    private const ALLOWED_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    private string $connection;
    private ?string $environmentConnection = null;

    public function __construct(
        private ?string $userEmail = null,
        ?string $connection = null
    ) {
        $this->connection = $connection ?? config('salesforce.default');
    }

    /**
     * Switch to a different Salesforce connection.
     */
    public function connection(string $name): self
    {
        $this->connection = $name;
        cache()->forget($this->getTokenCacheKey());
        return $this;
    }

    /**
     * Set a connection to be used only in specific environments.
     *
     * @param string $connection The connection name to use
     * @param string|array $environments The environment(s) where this connection should be used
     */
    public function whenEnvironment(string $connection, string|array $environments): self
    {
        $currentEnv = app()->environment();
        $environments = (array) $environments;

        if (in_array($currentEnv, $environments, true)) {
            $this->environmentConnection = $connection;
            cache()->forget($this->getTokenCacheKey());
        }

        return $this;
    }

    /**
     * Get the current connection name.
     */
    public function getConnection(): string
    {
        return $this->environmentConnection ?? $this->connection;
    }

    /**
     * Get the configuration for the current connection.
     *
     * @throws SalesforceException
     */
    private function getConfig(?string $key = null): mixed
    {
        $connectionName = $this->getConnection();
        $config = config("salesforce.connections.{$connectionName}");

        if ($config === null) {
            // If environment-specific connection fails, fall back to default
            if ($this->environmentConnection !== null) {
                $this->environmentConnection = null;
                return $this->getConfig($key);
            }

            throw new SalesforceException("Salesforce connection [{$connectionName}] not configured.");
        }

        return $key === null ? $config : ($config[$key] ?? null);
    }

    private function getTokenCacheKey(): string
    {
        return self::TOKEN_CACHE_KEY . '.' . $this->getConnection();
    }

    /**
     * Magic method to handle HTTP requests dynamically
     *
     * @throws SalesforceException
     */
    public function __call(string $method, array $arguments): Response
    {
        if (! in_array($method, self::ALLOWED_METHODS, true)) {
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

    public function setEmail(string $email): self
    {
        $this->userEmail = $email;

        return $this;
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
                cache()->forget($this->getTokenCacheKey());
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

    private function getBaseUrl(): string
    {
        $apexUri = $this->getConfig('apex_uri');
        if (! is_string($apexUri)) {
            throw new SalesforceException('Invalid apex_uri configuration');
        }

        // Add port 8443 for certificate-based connections
        if ($this->getConfig('certificate') &&
            $this->getConfig('certificate_key') &&
            ! Str::contains($apexUri, '.com:8443')
        ) {
            $apexUri = Str::replaceFirst('.com', '.com:8443', $apexUri);
        }

        return rtrim($apexUri, '/');
    }

    /**
     * @throws SalesforceException
     */
    private function getToken(): string
    {
        try {
            return cache()->remember(
                $this->getTokenCacheKey(),
                self::TOKEN_CACHE_TTL,
                fn (): string => $this->refreshToken()
            );
        } catch (Exception $e) {
            Log::error('Failed to obtain Salesforce token', ['error' => $e->getMessage()]);
            throw new SalesforceException($e->getMessage(), 500, $e);
        }
    }

    /**
     * @throws SalesforceException
     */
    private function refreshToken(): string
    {
        $tokenUri = $this->getConfig('token_uri');
        if (! is_string($tokenUri)) {
            throw new SalesforceException('Invalid token_uri configuration');
        }

        try {
            $response = Http::asForm()->post($tokenUri, [
                'grant_type' => 'password',
                'client_id' => $this->getConfig('client_id'),
                'client_secret' => $this->getConfig('client_secret'),
                'username' => $this->getConfig('username'),
                'password' => $this->getConfig('password').$this->getConfig('security_token'),
            ]);

            if (! $response->successful()) {
                throw new SalesforceException(
                    'Failed to refresh token: '.$response->body(),
                    $response->status()
                );
            }

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '' || $token === '0') {
                throw new SalesforceException(
                    'Invalid token received from Salesforce',
                    $response->status()
                );
            }

            return $token;

        } catch (Exception $e) {
            if ($e instanceof SalesforceException) {
                throw $e;
            }

            throw new SalesforceException(
                'Failed to refresh token: '.$e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * Build headers for the request including optional application authentication.
     */
    private function buildHeaders(array $additionalHeaders): array
    {
        $headers = [];

        // Add optional application authentication headers
        $appUuid = $this->getConfig('app_uuid');
        $appKey = $this->getConfig('app_key');

        if ($appUuid && $appKey) {
            $headers['x-app-uuid'] = $appUuid;
            $headers['x-app-key'] = $appKey;
        }

        // Add user email if available
        if ($this->userEmail ?? (Auth::user()?->email ?? null)) {
            $headers['x-user-email'] = $this->userEmail ?? Auth::user()?->email;
        }

        return array_merge($headers, $additionalHeaders);
    }

    private function getRequestOptions(): array
    {
        $certificate = $this->getConfig('certificate');
        $certificateKey = $this->getConfig('certificate_key');

        // If neither is set, return empty options
        if (! $certificate && ! $certificateKey) {
            return [];
        }

        // If one is set but not the other, throw exception
        if (($certificate && ! $certificateKey) || (! $certificate && $certificateKey)) {
            throw new SalesforceException(
                'Both certificate and certificate_key must be provided if using certificate authentication'
            );
        }

        $certificatePath = storage_path('certificates').DIRECTORY_SEPARATOR.$certificate;
        $certificateKeyPath = storage_path('certificates').DIRECTORY_SEPARATOR.$certificateKey;

        // Only add certificate options if the files exist
        if (! File::exists($certificatePath) || ! File::exists($certificateKeyPath)) {
            throw new SalesforceException(
                'Certificate files not found. Please ensure they exist in the storage/certificates directory.'
            );
        }

        return [
            'curl' => [
                CURLOPT_SSLCERT => $certificatePath,
                CURLOPT_SSLKEY => $certificateKeyPath,
                CURLOPT_VERBOSE => config('app.debug'),
            ],
        ];
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
        if (! $e instanceof SalesforceException) {
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
}
