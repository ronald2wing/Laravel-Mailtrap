<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap;

use Ronald2Wing\LaravelMailtrap\Exceptions\InvalidConfigurationException;

final readonly class MailtrapConfig
{
    private const DEFAULT_HTTP_OPTIONS = [
        'connect_timeout' => 10,
        'timeout' => 30,
    ];

    /**
     * @var array<string, mixed>
     */
    public array $httpOptions;

    /**
     * @param  array<string, mixed>  $httpOptions
     */
    public function __construct(
        public string $token,
        public MailtrapEndpoint $endpoint = MailtrapEndpoint::Transactional,
        public ?int $inboxId = null,
        public ?string $baseUrl = null,
        array $httpOptions = [],
    ) {
        $this->httpOptions = [
            ...self::DEFAULT_HTTP_OPTIONS,
            ...$httpOptions,
        ];

        $this->validate();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $endpointValue = (string) ($config['endpoint'] ?? MailtrapEndpoint::Transactional->value);

        $endpoint = MailtrapEndpoint::tryFrom($endpointValue);

        if ($endpoint === null) {
            $raw = $config['endpoint'] ?? null;

            throw new InvalidConfigurationException(sprintf(
                'Unknown Mailtrap endpoint "%s". Valid values: transactional, bulk, sandbox.',
                is_string($raw) ? $raw : get_debug_type($raw),
            ));
        }

        return new self(
            token: (string) ($config['token'] ?? ''),
            endpoint: $endpoint,
            inboxId: self::resolveInboxId($config),
            baseUrl: self::resolveBaseUrl($config),
            httpOptions: self::resolveHttpOptions($config),
        );
    }

    public function apiUrl(): string
    {
        $base = rtrim($this->baseUrl ?? $this->endpoint->baseUrl(), '/');
        $path = $base.'/api/send';

        return $this->inboxId !== null
            ? $path.'/'.$this->inboxId
            : $path;
    }

    private function validate(): void
    {
        if ($this->token === '') {
            throw new InvalidConfigurationException('Mailtrap API token cannot be empty');
        }

        if ($this->baseUrl !== null && ! filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidConfigurationException(
                sprintf('Invalid Mailtrap base_url "%s" — must be a valid URL', $this->baseUrl),
            );
        }

        if ($this->endpoint->requiresInboxId() && $this->inboxId === null) {
            throw new InvalidConfigurationException('Sandbox endpoint requires an inbox_id');
        }

        if ($this->inboxId !== null && $this->inboxId <= 0) {
            throw new InvalidConfigurationException(
                sprintf('inbox_id must be a positive integer, %d given', $this->inboxId),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function resolveInboxId(array $config): ?int
    {
        if (! array_key_exists('inbox_id', $config) || $config['inbox_id'] === null) {
            return null;
        }

        if (! is_int($config['inbox_id'])) {
            throw new InvalidConfigurationException('The "inbox_id" configuration key must be an integer.');
        }

        return $config['inbox_id'];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function resolveBaseUrl(array $config): ?string
    {
        if (! array_key_exists('base_url', $config) || $config['base_url'] === null) {
            return null;
        }

        $baseUrl = $config['base_url'];

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new InvalidConfigurationException('The "base_url" configuration key must be a non-empty string.');
        }

        return $baseUrl;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function resolveHttpOptions(array $config): array
    {
        if (! array_key_exists('http', $config)) {
            return [];
        }

        if (! is_array($config['http'])) {
            throw new InvalidConfigurationException('The "http" configuration key must be an array.');
        }

        return $config['http'];
    }
}
