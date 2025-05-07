<?php

declare(strict_types=1);

namespace Antogkou\LaravelSalesforce;

use Antogkou\LaravelSalesforce\Exceptions\SalesforceException;
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
            $config = $app['config'];
            $connection = $config->get('salesforce.default');

            // If the default connection doesn't exist in the config, try to use the first available connection
            if (! $config->has("salesforce.connections.{$connection}")) {
                $connections = $config->get('salesforce.connections', []);
                if (empty($connections)) {
                    throw new RuntimeException('No Salesforce connections configured.');
                }
                $connection = array_key_first($connections);
            }

            $this->ensureConfigExists($app['config'], $connection);

            return new ApexClient(
                userEmail: $config->get("salesforce.connections.{$connection}.default_user_email"),
                connection: $connection
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
    private function ensureConfigExists(Repository $config, string $connection): void
    {
        if (! $config->has("salesforce.connections.{$connection}")) {
            throw new RuntimeException("Salesforce connection [{$connection}] not configured.");
        }

        $requiredKeys = [
            'apex_uri',
            'token_uri',
            'client_id',
            'client_secret',
            'username',
            'password',
            'security_token',
        ];

        $missingKeys = array_filter($requiredKeys, fn (string $key): bool => empty($config->get("salesforce.connections.{$connection}.{$key}"))
        );

        if ($missingKeys !== []) {
            throw new RuntimeException(sprintf(
                'Missing required Salesforce configuration keys for connection [%s]: %s',
                $connection,
                implode(', ', $missingKeys)
            ));
        }

        // Validate URLs
        $urls = ['apex_uri', 'token_uri'];
        foreach ($urls as $key) {
            $url = $config->get("salesforce.connections.{$connection}.{$key}");
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException(
                    "Invalid URL format for salesforce.connections.{$connection}.{$key}: {$url}"
                );
            }
        }

        // Validate certificate configuration consistency
        $this->validateCertificateConfig($config, $connection);
    }

    private function validateCertificateConfig(Repository $config, string $connection): void
    {
        $certConfig = [
            'certificate' => $config->get("salesforce.connections.{$connection}.certificate"),
            'certificate_key' => $config->get("salesforce.connections.{$connection}.certificate_key"),
        ];

        if (array_filter($certConfig) !== [] && array_filter($certConfig) !== $certConfig) {
            throw new SalesforceException(
                "Both certificate and certificate_key must be provided for connection [{$connection}] if using certificate authentication"
            );
        }
    }
}
