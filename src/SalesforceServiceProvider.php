<?php

declare(strict_types=1);

namespace Antogkou\LaravelSalesforce;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class SalesforceServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/salesforce.php',
            'salesforce'
        );

        $this->app->singleton('salesforce', function (Container $app): ApexClient {
            $this->ensureConfigExists($app['config']);

            return new ApexClient(
                userEmail: $app['config']->get('salesforce.default_user_email')
            );
        });

        $this->app->alias('salesforce', ApexClient::class);
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/salesforce.php' => $this->app->configPath('salesforce.php'),
            ], 'salesforce-config');

            $this->publishes([
                __DIR__.'/../storage/certificates' => storage_path('certificates'),
            ], 'salesforce-certificates');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'salesforce',
            ApexClient::class,
        ];
    }

    /**
     * Ensure all required configuration values exist.
     *
     * @throws RuntimeException
     */
    private function ensureConfigExists(Repository $config): void
    {
        $requiredKeys = [
            'apex_uri',
            'token_uri',
            'client_id',
            'client_secret',
            'username',
            'password',
            'security_token',
        ];

        $missingKeys = array_filter($requiredKeys, fn (string $key): bool => empty($config->get("salesforce.{$key}"))
        );

        if ($missingKeys !== []) {
            throw new RuntimeException(sprintf(
                'Missing required Salesforce configuration keys: %s',
                implode(', ', $missingKeys)
            ));
        }

        // Validate URLs
        $urls = ['apex_uri', 'token_uri'];
        foreach ($urls as $key) {
            $url = $config->get("salesforce.{$key}");
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException(
                    "Invalid URL format for salesforce.{$key}: {$url}"
                );
            }
        }

        // Validate certificate configuration consistency
        $certConfig = [
            'certificate' => $config->get('salesforce.certificate'),
            'certificate_key' => $config->get('salesforce.certificate_key'),
        ];

        if (array_filter($certConfig) !== [] && array_filter($certConfig) !== $certConfig) {
            throw new RuntimeException(
                'Both certificate and certificate_key must be provided if using certificate authentication'
            );
        }
    }
}
