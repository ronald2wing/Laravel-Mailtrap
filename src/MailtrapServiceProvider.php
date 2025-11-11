<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Mailtrap Service Provider for Laravel
 *
 * Registers the Mailtrap API transport with Laravel's mail system,
 * providing seamless integration with the mail manager and configuration.
 *
 * @author Ronald Wong <ronald2wing@gmail.com>
 * @license MIT https://opensource.org/licenses/MIT
 *
 * @link   https://github.com/ronald2wing/laravel-mailtrap
 */
class MailtrapServiceProvider extends ServiceProvider
{
    /** Configuration key for Mailtrap settings */
    private const CONFIG_KEY = 'services.mailtrap';

    /** Default HTTP connection timeout in seconds */
    private const DEFAULT_TIMEOUT = 60;

    /**
     * Bootstrap the service provider
     *
     * Registers the Mailtrap transport driver with Laravel's mail manager.
     */
    public function boot(): void
    {
        $this->registerMailtrapTransport();
    }

    /**
     * Register the Mailtrap transport with the mail manager
     */
    protected function registerMailtrapTransport(): void
    {
        /** @var MailManager $mailManager */
        $mailManager = $this->app->get('mail.manager');

        $mailManager->extend('mailtrap', function (array $config = []): MailtrapTransport {
            $mailtrapConfig = $this->getMailtrapConfiguration();
            $this->validateConfiguration($mailtrapConfig);

            $apiToken = (string) $mailtrapConfig['token'];
            $apiEndpoint = isset($mailtrapConfig['endpoint']) ? (string) $mailtrapConfig['endpoint'] : null;

            return new MailtrapTransport(
                $this->createHttpClient($mailtrapConfig),
                $apiToken,
                $apiEndpoint
            );
        });
    }

    /**
     * Get Mailtrap configuration from the application config
     *
     * @return array<string, mixed> Mailtrap configuration array
     */
    protected function getMailtrapConfiguration(): array
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app->get('config');

        /** @var array<string, mixed> $mailtrapConfig */
        $mailtrapConfig = $config->get(self::CONFIG_KEY, []);

        return $mailtrapConfig;
    }

    /**
     * Validate the Mailtrap configuration
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     *
     * @throws InvalidArgumentException If configuration is invalid
     */
    protected function validateConfiguration(array $config): void
    {
        if (empty($config['token'])) {
            throw new InvalidArgumentException(
                'Mailtrap API token is missing. Please configure it in config/services.php '.
                'under the "mailtrap.token" key or set MAILTRAP_TOKEN in your .env file.'
            );
        }

        if (! is_string($config['token'])) {
            throw new InvalidArgumentException(
                'Mailtrap API token must be a string. Please check your configuration.'
            );
        }
    }

    /**
     * Create a configured HTTP client instance
     *
     * @param  array<string, mixed>  $config  Mailtrap configuration array
     * @return HttpClient Configured Guzzle HTTP client
     */
    protected function createHttpClient(array $config): HttpClient
    {
        $guzzleOptions = $config['guzzle'] ?? [];

        if (! is_array($guzzleOptions)) {
            $guzzleOptions = [];
        }

        return new HttpClient(
            Arr::add($guzzleOptions, 'connect_timeout', self::DEFAULT_TIMEOUT)
        );
    }
}
