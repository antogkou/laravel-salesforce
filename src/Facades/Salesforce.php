<?php

declare(strict_types=1);

namespace Antogkou\LaravelSalesforce\Facades;

use Antogkou\LaravelSalesforce\ApexClient;
use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Response get(string $url, array $query = [], array $additionalHeaders = [])
 * @method static Response post(string $url, array $data, array $additionalHeaders = [])
 * @method static Response put(string $url, array $data, array $additionalHeaders = [])
 * @method static Response patch(string $url, array $data, array $additionalHeaders = [])
 * @method static Response delete(string $url, array $additionalHeaders = [])
 * @method static self setEmail(string $email)
 * @method static self connection(string $name)
 * @method static self whenEnvironment(string $connection, string|array $environments)
 * @method static string getConnection()
 *
 * Generic request method for dynamic HTTP methods
 * @method static Response __call(string $method, array $arguments)
 *
 * Common response methods available after making a request
 * @method static array|null json(?string $key = null, mixed $default = null)
 * @method static string body()
 * @method static int status()
 * @method static bool successful()
 * @method static bool failed()
 * @method static bool unauthorized()
 * @method static bool forbidden()
 * @method static bool notFound()
 *
 * @throws SalesforceException When API request fails or configuration is invalid
 * @throws RequestException When HTTP client encounters an error
 *
 * @see ApexClient
 *
 * @mixin ApexClient
 */
final class Salesforce extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'salesforce';
    }
}
