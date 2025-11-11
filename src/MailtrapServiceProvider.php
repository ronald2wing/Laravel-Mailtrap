<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap;

use Illuminate\Http\Client\Factory;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use Ronald2Wing\LaravelMailtrap\Exceptions\InvalidConfigurationException;

class MailtrapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mailtrap.php', 'mailtrap');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mailtrap.php' => config_path('mailtrap.php'),
            ], 'mailtrap-config');
        }

        $this->app->make(MailManager::class)->extend('mailtrap', $this->createTransport(...));
    }

    /**
     * @param  array<string, mixed>  $mailerConfig
     */
    private function createTransport(array $mailerConfig): MailtrapTransport
    {
        return new MailtrapTransport(
            http: $this->app->make(Factory::class),
            config: $this->resolveConfig($mailerConfig),
        );
    }

    /**
     * @param  array<string, mixed>  $mailerConfig
     */
    private function resolveConfig(array $mailerConfig): MailtrapConfig
    {
        $key = $mailerConfig['config'] ?? 'mailtrap';
        $raw = config($key);

        if (! is_array($raw)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration key "%s" must resolve to an array, got %s.',
                $key,
                get_debug_type($raw),
            ));
        }

        return MailtrapConfig::fromArray($raw);
    }
}
