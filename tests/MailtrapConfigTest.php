<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ronald2Wing\LaravelMailtrap\Exceptions\InvalidConfigurationException;
use Ronald2Wing\LaravelMailtrap\MailtrapConfig;
use Ronald2Wing\LaravelMailtrap\MailtrapEndpoint;

final class MailtrapConfigTest extends TestCase
{
    use BuildsTestEmails;

    #[Test]
    public function rejects_empty_token(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Mailtrap API token cannot be empty');

        new MailtrapConfig(token: '');
    }

    #[Test]
    public function default_endpoint_resolves_to_transactional_send_url(): void
    {
        $config = new MailtrapConfig(token: 'fake');

        $this->assertSame(
            'https://send.api.mailtrap.io/api/send',
            $config->apiUrl(),
        );
    }

    #[Test]
    public function bulk_endpoint_url(): void
    {
        $config = new MailtrapConfig(
            token: self::TEST_TOKEN,
            endpoint: MailtrapEndpoint::Bulk,
        );

        $this->assertSame(
            'https://bulk.api.mailtrap.io/api/send',
            $config->apiUrl(),
        );
    }

    #[Test]
    public function sandbox_endpoint_url(): void
    {
        $config = new MailtrapConfig(
            token: self::TEST_TOKEN,
            endpoint: MailtrapEndpoint::Sandbox,
            inboxId: 42,
        );

        $this->assertSame(
            'https://sandbox.api.mailtrap.io/api/send/42',
            $config->apiUrl(),
        );
    }

    #[Test]
    public function base_url_override_strips_trailing_slash(): void
    {
        $config = new MailtrapConfig(token: 'fake', baseUrl: 'https://proxy.example.com/');

        $this->assertSame('https://proxy.example.com/api/send', $config->apiUrl());
    }

    #[Test]
    public function base_url_override_uses_provided_url(): void
    {
        $config = new MailtrapConfig(
            token: self::TEST_TOKEN,
            endpoint: MailtrapEndpoint::Sandbox,
            inboxId: 7,
            baseUrl: 'https://custom-proxy.example.com',
        );

        $this->assertSame(
            'https://custom-proxy.example.com/api/send/7',
            $config->apiUrl(),
        );
    }

    #[Test]
    public function invalid_base_url_throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a valid URL');

        new MailtrapConfig(token: self::TEST_TOKEN, baseUrl: 'not-a-valid-url');
    }

    #[Test]
    public function sandbox_without_inbox_id_throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Sandbox endpoint requires an inbox_id');

        new MailtrapConfig(
            token: self::TEST_TOKEN,
            endpoint: MailtrapEndpoint::Sandbox,
        );
    }

    #[Test]
    public function sandbox_with_inbox_id_includes_it_in_url(): void
    {
        $config = new MailtrapConfig(
            token: self::TEST_TOKEN,
            endpoint: MailtrapEndpoint::Sandbox,
            inboxId: 42,
        );

        $this->assertSame(
            'https://sandbox.api.mailtrap.io/api/send/42',
            $config->apiUrl(),
        );
    }

    #[Test]
    public function transactional_with_inbox_id_appends_it(): void
    {
        $config = new MailtrapConfig(
            token: self::TEST_TOKEN,
            inboxId: 99,
        );

        $this->assertSame(
            'https://send.api.mailtrap.io/api/send/99',
            $config->apiUrl(),
        );
    }

    #[Test]
    public function bulk_endpoint_without_inbox_id_works(): void
    {
        $config = new MailtrapConfig(
            token: self::TEST_TOKEN,
            endpoint: MailtrapEndpoint::Bulk,
        );

        $this->assertSame(
            'https://bulk.api.mailtrap.io/api/send',
            $config->apiUrl(),
        );
    }

    #[Test]
    public function from_array_throws_when_token_missing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Mailtrap API token cannot be empty');

        MailtrapConfig::fromArray([]);
    }

    #[Test]
    public function from_array_throws_on_unknown_endpoint(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown Mailtrap endpoint');

        MailtrapConfig::fromArray([
            'token' => 'my-token',
            'endpoint' => 'invalid-value',
        ]);
    }

    #[Test]
    public function from_array_throws_on_url_as_endpoint(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown Mailtrap endpoint');

        MailtrapConfig::fromArray([
            'token' => 'my-token',
            'endpoint' => 'https://custom.api.example.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $expectedProperties
     */
    #[Test]
    #[DataProvider('fromArrayProvider')]
    public function from_array_parses_input(array $config, array $expectedProperties): void
    {
        $result = MailtrapConfig::fromArray($config);

        foreach ($expectedProperties as $prop => $expected) {
            $this->assertSame($expected, $result->{$prop});
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function fromArrayProvider(): array
    {
        return [
            'basic' => [
                ['token' => 'my-token'],
                ['token' => 'my-token', 'endpoint' => MailtrapEndpoint::Transactional, 'inboxId' => null, 'baseUrl' => null],
            ],
            'reads inbox_id' => [
                ['token' => 'my-token', 'inbox_id' => 42],
                ['inboxId' => 42],
            ],
            'reads http config' => [
                ['token' => 'my-token', 'http' => ['timeout' => 45]],
                ['httpOptions' => ['connect_timeout' => 10, 'timeout' => 45]],
            ],
            'all keys' => [
                [
                    'token' => 'my-token',
                    'endpoint' => 'bulk',
                    'inbox_id' => 7,
                    'base_url' => 'https://proxy.example.com',
                    'http' => ['connect_timeout' => 5],
                ],
                [
                    'token' => 'my-token',
                    'endpoint' => MailtrapEndpoint::Bulk,
                    'inboxId' => 7,
                    'baseUrl' => 'https://proxy.example.com',
                ],
            ],
        ];
    }

    #[Test]
    public function inbox_id_must_be_positive(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('inbox_id must be a positive integer, 0 given');

        new MailtrapConfig(token: self::TEST_TOKEN, inboxId: 0);
    }

    #[Test]
    public function from_array_throws_on_non_array_http(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "http" configuration key must be an array.');

        MailtrapConfig::fromArray([
            'token' => 'my-token',
            'http' => 'not-an-array',
        ]);
    }

    #[Test]
    public function from_array_throws_on_non_int_inbox_id(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "inbox_id" configuration key must be an integer.');

        MailtrapConfig::fromArray([
            'token' => 'my-token',
            'inbox_id' => 'not-an-int',
        ]);
    }

    #[Test]
    #[DataProvider('defaultHttpOptionsProvider')]
    public function default_client_options(string $key, int $expected): void
    {
        $config = new MailtrapConfig(token: self::TEST_TOKEN);

        $this->assertSame($expected, $config->httpOptions[$key]);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function defaultHttpOptionsProvider(): array
    {
        return [
            'connect_timeout' => ['connect_timeout', 10],
            'timeout' => ['timeout', 30],
        ];
    }

    #[Test]
    public function user_can_override_timeouts(): void
    {
        $config = new MailtrapConfig(
            token: self::TEST_TOKEN,
            httpOptions: ['connect_timeout' => 5, 'timeout' => 15],
        );

        $this->assertSame(5, $config->httpOptions['connect_timeout']);
        $this->assertSame(15, $config->httpOptions['timeout']);
    }

    #[Test]
    public function extra_http_options_are_passed_through(): void
    {
        $config = new MailtrapConfig(
            token: self::TEST_TOKEN,
            httpOptions: ['verify' => false, 'proxy' => 'http://proxy:8080'],
        );

        $this->assertFalse($config->httpOptions['verify']);
        $this->assertSame('http://proxy:8080', $config->httpOptions['proxy']);
    }
}
