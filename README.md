# Laravel Salesforce Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/antogkou/laravel-salesforce/Tests?label=tests)](https://github.com/antogkou/laravel-salesforce/actions?query=workflow%3ATests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)
[![License](https://img.shields.io/packagist/l/antogkou/laravel-salesforce.svg?style=flat-square)](https://packagist.org/packages/antogkou/laravel-salesforce)

A Laravel package for seamless Salesforce API integration.

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Composer 2.0 or higher

## Version Compatibility

| Laravel | PHP          | Package |
|---------|--------------|---------|
| 10.x    | 8.1, 8.2, 8.3| 1.x     |
| 11.x    | 8.2, 8.3    | 1.x     |

## Installation

You can install the package via composer:

```bash
composer require antogkou/laravel-salesforce
```

You can publish the config file with:
```bash
php artisan vendor:publish --tag="salesforce-config"
```

## Configuration

Add the following environment variables to your `.env` file:

```env
SF_CLIENT_ID=your-client-id
SF_CLIENT_SECRET=your-client-secret
SF_USERNAME=your-username
SF_PASSWORD=your-password
SF_SECURITY_TOKEN=your-security-token
SF_TOKEN_URI=https://test.salesforce.com/services/oauth2/token
SF_APEX_URI=https://test.salesforce.com/services/apexrest
SF_APP_UUID=your-app-uuid
SF_APP_KEY=your-app-key
```

## Usage

```php
use Antogkou\LaravelSalesforce\Facades\Salesforce;

// Get data
$response = Salesforce::get('/endpoint');

// Post data
$response = Salesforce::post('/endpoint', ['data' => 'value']);

// Set custom email
$response = Salesforce::setEmail('user@example.com')->get('/endpoint');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Your Name](https://github.com/yourusername)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
