# Laravel Salesforce Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/antogkou/laravel-salesforce/Tests?label=tests)](https://github.com/antogkou/laravel-salesforce/actions?query=workflow%3ATests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)
[![License](https://img.shields.io/packagist/l/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)

A Laravel package for seamless Salesforce API integration, providing an elegant way to interact with Salesforce's REST
APIs.

## Features

- 🚀 Simple and intuitive API
- 🔑 Flexible authentication options
- 🔒 Supports custom Apex class authentication
- 🌐 OAuth 2.0 integration
- 📦 Automatic token management
- 🔐 Optional certificate-based authentication
- ⚡ Request/Response interceptors

## Requirements

- PHP 8.2 or higher (Compatible with PHP 8.2, 8.3, 8.4)
- Laravel 11.0 or higher (Laravel 12 support is experimental)
- Composer 2.0 or higher

## Version Compatibility

| Laravel | PHP           | Package |
|---------|---------------|---------|
| 10.x    | 8.1, 8.2, 8.3 | 1.x     |
| 11.x    | 8.2, 8.3      | 2.x     |
| 11.x    | 8.4           | 3.x     |
| 12.x    | 8.2, 8.3, 8.4 | 3.x     |

## Installation

1. Install the package via composer:

```bash
composer require antogkou/laravel-salesforce
```

2. Publish the configuration:

```bash
# Publish config file
php artisan vendor:publish --tag="salesforce-config"

# If using certificate authentication
php artisan vendor:publish --tag="salesforce-certificates"
```

## Configuration

### Multiple Connections

This package supports multiple Salesforce connections. You can configure them in your `.env` file and
`config/salesforce.php`:

```env
# Default connection
SALESFORCE_CONNECTION=default

# Default connection credentials
SALESFORCE_CLIENT_ID=your-client-id
SALESFORCE_CLIENT_SECRET=your-client-secret
SALESFORCE_USERNAME=your-username
SALESFORCE_PASSWORD=your-password
SALESFORCE_SECURITY_TOKEN=your-security-token
SALESFORCE_APEX_URI=https://salesforce-instance.com/services/apexrest

# Sandbox connection credentials
SALESFORCE_SANDBOX_CLIENT_ID=sandbox-client-id
SALESFORCE_SANDBOX_CLIENT_SECRET=sandbox-client-secret
SALESFORCE_SANDBOX_USERNAME=sandbox-username
SALESFORCE_SANDBOX_PASSWORD=sandbox-password
SALESFORCE_SANDBOX_SECURITY_TOKEN=sandbox-security-token
SALESFORCE_SANDBOX_APEX_URI=https://sandbox-instance.com/services/apexrest
```

You can switch between connections at runtime:

```php
use Antogkou\LaravelSalesforce\Facades\Salesforce;

// Use the default connection
$response = Salesforce::get('/endpoint');

// Switch to sandbox connection
$response = Salesforce::connection('sandbox')->get('/endpoint');

// Switch back to default
$response = Salesforce::connection('default')->get('/endpoint');

// Chain with other methods
$response = Salesforce::connection('sandbox')
    ->setEmail('user@example.com')
    ->get('/endpoint');

// Use environment-specific connections
$response = Salesforce::whenEnvironment('sandbox', 'staging')
    ->get('/endpoint'); // Uses 'sandbox' connection only in staging environment

// Use environment-specific connections with multiple environments
$response = Salesforce::whenEnvironment('sandbox', ['staging', 'testing'])
    ->get('/endpoint'); // Uses 'sandbox' connection in both staging and testing environments

// If the environment-specific connection is not configured, falls back to default
$response = Salesforce::whenEnvironment('sandbox', 'production')
    ->get('/endpoint'); // Uses default connection in production
```

Each connection can have its own:

- OAuth credentials
- API endpoints
- Application authentication
- Certificate configuration
- Default user email

The package will always use the default connection unless explicitly changed using `connection()` or
`whenEnvironment()`. If an environment-specific connection is set but not configured, it will automatically fall back to
the default connection.

### Required Configuration

Add these essential environment variables to your `.env` file:

```env
# Required: Salesforce OAuth Credentials
SALESFORCE_CLIENT_ID=your-client-id
SALESFORCE_CLIENT_SECRET=your-client-secret
SALESFORCE_USERNAME=your-username
SALESFORCE_PASSWORD=your-password
SALESFORCE_SECURITY_TOKEN=your-security-token
SALESFORCE_APEX_URI=https://salesforce-instance.com/services/apexrest

# Optional: Default endpoints (these defaults are for sandboxes)
SALESFORCE_TOKEN_URI=https://test.salesforce.com/services/oauth2/token
```

### Optional: Custom Apex Authentication

If your Salesforce Apex classes implement custom application-level authentication, you can configure it using:

```env
# Optional: Custom Apex Authentication
SALESFORCE_APP_UUID=your-app-uuid
SALESFORCE_APP_KEY=your-app-key
```

This adds `x-app-uuid` and `x-app-key` headers to your requests, which you can validate in your Apex classes:

```apex
@RestResource(urlMapping='/your-endpoint/*')
global with sharing class YourApexClass {
    @HttpGet
    global static Response doGet() {
        // Validate application credentials
        String appUuid = RestContext.request.headers.get('x-app-uuid');
        String appKey = RestContext.request.headers.get('x-app-key');
        
        if (!YourAuthService.validateApp(appUuid, appKey)) {
            throw new CustomException('Invalid application credentials');
        }
        
        // Your endpoint logic...
    }
}
```

### Optional: Certificate Authentication

For certificate-based authentication:

```env
# Optional: Certificate Authentication
SALESFORCE_CERTIFICATE=cert.pem
SALESFORCE_CERTIFICATE_KEY=cert.key
```

### Optional: Default User Context

```env
# Optional: Default User Email
SALESFORCE_DEFAULT_USER_EMAIL=default@example.com
```

## Basic Usage

```php
use Antogkou\LaravelSalesforce\Facades\Salesforce;

// Basic request
$response = Salesforce::get('/endpoint');

// With custom user context
$response = Salesforce::setEmail('user@example.com')
    ->get('/endpoint');

// With custom headers
$response = Salesforce::post('/endpoint', $data, [
    'Custom-Header' => 'Value'
]);
```

### Advanced Usage

```php
// Set custom user email for the request
$response = Salesforce::setEmail('user@example.com')
    ->get('/endpoint');

// With query parameters
$response = Salesforce::get('/endpoint', [
    'limit' => 10,
    'offset' => 0
]);

// With custom headers
$response = Salesforce::post('/endpoint', $data, [
    'Custom-Header' => 'Value'
]);

// Handling responses
$response = Salesforce::get('/endpoint');

if ($response->successful()) {
    $data = $response->json();
    $status = $response->status();
} else {
    $error = $response->json('error');
}
```

### Error Handling

```php
use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
use Illuminate\Http\Client\RequestException;

try {
    $response = Salesforce::get('/endpoint');
} catch (SalesforceException $e) {
    // Handle Salesforce-specific errors
    $details = $e->getDetails(); // Array of error details
    $status = $e->getCode();     // HTTP status code
} catch (RequestException $e) {
    // Handle HTTP client errors
}
```

## Testing

```bash
composer test
```

## Logging

The package automatically logs all API errors and failed requests. Logs include:

- Request method and URL
- Request data
- Response status and body
- Laravel route information
- Stack trace for debugging

## Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/my-new-feature`
3. Commit your changes: `git commit -am 'Add new feature'`
4. Push to the branch: `git push origin feature/my-new-feature`
5. Submit a pull request

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

If you discover any security-related issues, please email [your-email] instead of using the issue tracker. All security
vulnerabilities will be promptly addressed.

Please review [our security policy](../../security/policy) for more information.

## Credits

- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
