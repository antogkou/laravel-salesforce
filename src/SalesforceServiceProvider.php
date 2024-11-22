<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Integrations\ApexClient;
use Illuminate\Support\ServiceProvider;

class SalesforceServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->mergeConfigFrom(__DIR__ . '/../../config/salesforce.php', 'salesforce');

    $this->app->singleton('salesforce', function ($app) {
      return new ApexClient(
        userEmail: null,
        request: $app['request']
      );
    });
  }

  public function boot(): void
  {
    $this->publishes([
      __DIR__ . '/../../config/salesforce.php' => config_path('salesforce.php'),
    ], 'salesforce-config');
  }
}
