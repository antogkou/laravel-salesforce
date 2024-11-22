# Laravel Salesforce Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/antogkou/laravel-salesforce/Tests?label=tests)](https://github.com/antogkou/laravel-salesforce/actions?query=workflow%3ATests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)
[![License](https://img.shields.io/packagist/l/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)

A Laravel package for seamless Salesforce API integration, providing an elegant way to interact with Salesforce's REST
APIs.

## Features

- 🚀 Simple and intuitive API
- 🔒 Secure authentication handling
- 📦 Automatic token management
- 🔄 Retry mechanism for failed requests
- 📝 Comprehensive logging
- 🔐 Certificate-based authentication support
- ⚡ Request/Response interceptors

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Composer 2.0 or higher

## Version Compatibility

| Laravel | PHP           | Package |
|---------|---------------|---------|
| 10.x    | 8.1, 8.2, 8.3 | 1.x     |
| 11.x    | 8.2, 8.3      | 1.x     |

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

1. Add the following environment variables to your `.env` file:

```env
SALESFORCE_APP_UUID=your-app-uuid
SALESFORCE_APP_KEY=your-app-key
SALESFORCE_CLIENT_ID=your-client-id
SALESFORCE_CLIENT_SECRET=your-client-secret
SALESFORCE_USERNAME=your-username
SALESFORCE_PASSWORD=your-password
SALESFORCE_SECURITY_TOKEN=your-security-token
SALESFORCE_TOKEN_URI=https://test.salesforce.com/services/oauth2/token
SALESFORCE_APEX_URI=https://test.salesforce.com/services/apexrest
SALESFORCE_DEFAULT_USER_EMAIL=default@example.com

# Optional - For certificate-based authentication
SALESFORCE_CERTIFICATE=cert.pem
SALESFORCE_CERTIFICATE_KEY=cert.key
```

2. Configure certificate-based authentication (optional):

Place your certificates in the `storage/certificates` directory:

- `cert.pem`: Your SSL certificate
- `cert.key`: Your SSL private key

## Usage

### Basic Usage

```php
use Antogkou\LaravelSalesforce\Facades\Salesforce;

// GET request
$response = Salesforce::get('/endpoint');

// POST request with data
$response = Salesforce::post('/endpoint', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// PUT request
$response = Salesforce::put('/endpoint', ['status' => 'active']);

// PATCH request
$response = Salesforce::patch('/endpoint', ['partial' => 'update']);

// DELETE request
$response = Salesforce::delete('/endpoint');
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
