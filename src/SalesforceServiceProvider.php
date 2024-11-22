<?php

declare(strict_types=1);

namespace YourVendor\LaravelSalesforce;

use Illuminate\Support\ServiceProvider;

class SalesforceServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    // Register config
    $this->mergeConfigFrom(
      __DIR__ . '/../config/salesforce.php',
      'salesforce'
    );

    // Register singleton
    $this->app->singleton('salesforce', function ($app) {
      return new ApexClient(
        userEmail: null,
        request: $app['request']
      );
    });
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // Publish config
    if ($this->app->runningInConsole()) {
      $this->publishes([
        __DIR__ . '/../config/salesforce.php' => config_path('salesforce.php'),
      ], 'salesforce-config');
    }
  }
}
