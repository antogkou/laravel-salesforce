<?php

declare(strict_types=1);

// src/ApexClient.php

namespace Antogkou\LaravelSalesforce;

use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ApexClient
{
    private const TOKEN_CACHE_KEY = 'salesforceToken';

    private const TOKEN_CACHE_TTL = 28800;

    private readonly ?Request $request;

    public function __construct(private ?string $userEmail = null, ?Request $request = null)
    {
        $this->request = $request ?? request();
    }

    public function setEmail(string $email): self
    {
        $this->userEmail = $email;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $additionalHeaders
     *
     * @throws RequestException
     * @throws SalesforceException
     */
    public function get(string $url, array $query = [], array $additionalHeaders = []): Response
    {
        return $this->sendRequest(method: 'get', url: $url, query: $query, additionalHeaders: $additionalHeaders);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $additionalHeaders
     *
     * @throws RequestException
     * @throws SalesforceException
     */
    private function sendRequest(
        string $method,
        string $url,
        array $query = [],
        array $data = [],
        array $additionalHeaders = [],
    ): Response {
        $request = $this->request($additionalHeaders);

        try {
            $fullUrl = $this->buildUrl($url, $query);

            $response = $method === 'get' ? $request->$method($fullUrl) : $request->$method($fullUrl, $data);

            if ($response->unauthorized()) {
                cache()->forget(self::TOKEN_CACHE_KEY);
                $request = $this->request($additionalHeaders); // Get a new request with the new token

                $response = $request->$method($fullUrl, $data);
            }

            if ($response->failed()) {
                $errorBody = $response->json() ?? $response->body();
                $routeInfo = $this->getRouteInfo();

                Log::error('Salesforce API Error', [
                    'method' => $method,
                    'url' => $fullUrl,
                    'data' => $data,
                    'status' => $response->status(),
                    'response' => $errorBody,
                    'laravel_route' => $routeInfo,
                ]);

                throw new SalesforceException(
                    ($errorBody['message'] ?? 'Unknown Salesforce API error'),
                    $response->status(),
                    null,
                    [
                        'message' => $errorBody['message'] ?? 'Unknown Salesforce API error',
                        'method' => $method,
                        'url' => $fullUrl,
                        'data' => $data,
                        'status' => $response->status(),
                        'response' => $errorBody,
                        'laravel_route' => $routeInfo,
                    ]
                );
            }

            return $response;
        } catch (Exception $e) {
            if (!$e instanceof SalesforceException) {
                $routeInfo = $this->getRouteInfo();
                Log::error('Salesforce API Request Failed', [
                    'method' => $method,
                    'url' => $url,
                    'data' => $data,
                    'error' => $e->getMessage(),
                    'laravel_route' => $routeInfo,
                ]);
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, string>  $additionalHeaders
     *
     * @throws SalesforceException
     */
    private function request(array $additionalHeaders = []): PendingRequest
    {
        $headers = [
            'x-app-uuid' => config('salesforce.app_uuid'),
            'x-api-key' => config('salesforce.app_key'),
            'x-user-email' => $this->userEmail ?? (Auth::user()?->email ?? ''),
        ];
        $mergedHeaders = array_merge($headers, $additionalHeaders);

        return Http::baseUrl($this->baseUrl())
            ->withToken($this->token())
            ->withHeaders($mergedHeaders)
            ->withOptions($this->options());
    }

    private function baseUrl(): string
    {
        $apexUri = config('salesforce.apex_uri');
        if (!is_string($apexUri)) {
            throw new SalesforceException('Invalid apex_uri configuration');
        }

        if (!Str::contains($apexUri, '.com:8443') && Arr::exists($this->options(), 'curl')) {
            $apexUri = Str::replaceFirst('.com', '.com:8443', $apexUri);
        }

        return rtrim($apexUri, '/');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function options(): array
    {
        if (config('salesforce.certificate') && config('salesforce.certificate_key')) {
            return [
                'curl' => [
                    CURLOPT_SSLCERT => storage_path('certificates').DIRECTORY_SEPARATOR.config('salesforce.certificate'),
                    CURLOPT_SSLKEY => storage_path('certificates').DIRECTORY_SEPARATOR.config('salesforce.certificate_key'),
                    CURLOPT_VERBOSE => config('app.debug'),
                ],
            ];
        }

        return [];
    }

    private function token(): string
    {
        try {
            return cache()->remember(self::TOKEN_CACHE_KEY, self::TOKEN_CACHE_TTL,
                fn(): string => $this->refreshToken());
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
            if (is_string($token) && ($token !== '' && $token !== '0')) {
                return $token;
            }
        }

        // If we reach here, either the response was not successful or the token was invalid
        $errorMessage = $response->successful()
            ? 'Invalid token received from Salesforce'
            : 'Failed to refresh token: '.$response->body();

        throw new SalesforceException(
            $errorMessage,
            $response->status() ?: 500,
            null,
            ['response' => $response->json()]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $additionalHeaders
     *
     * @throws SalesforceException|RequestException
     */
    public function post(string $url, array $data, array $additionalHeaders = []): Response
    {
        return $this->sendRequest(method: 'post', url: $url, data: $data, additionalHeaders: $additionalHeaders);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function buildUrl(string $url, array $query = []): string
    {
        $baseUrl = $this->baseUrl();
        $fullUrl = Str::startsWith($url, ['http://', 'https://'])
            ? $url
            : $baseUrl.'/'.ltrim($url, '/');

        $request = Request::create($fullUrl);

        // Merge existing query parameters with new ones
        $mergedQuery = array_merge(
            $request->query->all(),
            $query
        );

        return $request->getSchemeAndHttpHost()
            .$request->getPathInfo()
            .($mergedQuery === [] ? '' : '?'.http_build_query($mergedQuery));
    }

    /**
     * @return array{uri: string, name: string|null, action: string|null}
     */
    private function getRouteInfo(): array
    {
        if (!$this->request instanceof Request) {
            return [
                'uri' => '',
                'name' => null,
                'action' => null,
            ];
        }

        $route = $this->request->route();

        // Early return if route is null
        if (!$route instanceof Route) {
            return [
                'uri' => $this->request->path(),
                'name' => null,
                'action' => null,
            ];
        }

        // Now TypeScript knows $route is definitely a Route instance
        $name = $route->getName();
        $action = $route->getActionName();

        return [
            'uri' => $this->request->path(),
            'name' => $name ?: null,
            'action' => $action ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $additionalHeaders
     *
     * @throws RequestException
     * @throws SalesforceException
     */
    public function put(string $url, array $data, array $additionalHeaders = []): Response
    {
        return $this->sendRequest(method: 'put', url: $url, data: $data, additionalHeaders: $additionalHeaders);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $additionalHeaders
     *
     * @throws RequestException
     * @throws SalesforceException
     */
    public function patch(string $url, array $data, array $additionalHeaders = []): Response
    {
        return $this->sendRequest(method: 'patch', url: $url, data: $data, additionalHeaders: $additionalHeaders);
    }

    /**
     * @param  array<string, string>  $additionalHeaders
     *
     * @throws RequestException
     * @throws SalesforceException
     */
    public function delete(string $url, array $additionalHeaders = []): Response
    {
        return $this->sendRequest(method: 'delete', url: $url, data: [], additionalHeaders: $additionalHeaders);
    }
}
