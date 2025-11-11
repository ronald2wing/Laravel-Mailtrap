<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Ronald2Wing\LaravelMailtrap\Exceptions\InvalidConfigurationException;
use Ronald2Wing\LaravelMailtrap\Exceptions\MailtrapApiException;
use Ronald2Wing\LaravelMailtrap\Exceptions\MailtrapTransportException;
use Ronald2Wing\LaravelMailtrap\MailtrapConfig;
use Ronald2Wing\LaravelMailtrap\MailtrapServiceProvider;
use Ronald2Wing\LaravelMailtrap\MailtrapTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\RawMessage;

final class MailtrapTransportTest extends TestCase
{
    use BuildsTestEmails;

    protected function getPackageProviders($app): array
    {
        return [MailtrapServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('mail.default', 'mailtrap');
        $app['config']->set('mail.mailers.mailtrap', ['transport' => 'mailtrap']);
        $app['config']->set('mail.from', [
            'address' => self::SENDER_EMAIL,
            'name' => self::SENDER_NAME,
        ]);
        $app['config']->set('mailtrap.token', self::TEST_TOKEN);
    }

    private function createTransport(): MailtrapTransport
    {
        return new MailtrapTransport(
            http: app(HttpFactory::class),
            config: new MailtrapConfig(token: self::TEST_TOKEN),
        );
    }

    private function assertThrowsApiException(int $expectedStatus, string $expectedBody, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected MailtrapApiException was not thrown');
        } catch (MailtrapApiException $e) {
            $this->assertSame($expectedStatus, $e->statusCode);
            $this->assertSame($expectedBody, $e->body);
        }
    }

    #[Test]
    public function sends_email_successfully(): void
    {
        Http::fake([
            self::API_URL => Http::response(['success' => true, 'message_ids' => ['msg_42']]),
        ]);

        $transport = $this->createTransport();
        $email = $this->basicEmail()->subject('Test')->text('Body');
        $transport->send($email);

        Http::assertSent(function (Request $request) {
            return $request->url() === self::API_URL
                && $request->method() === 'POST'
                && $request->header('Api-Token')[0] === self::TEST_TOKEN;
        });
    }

    #[Test]
    public function throws_on_non_email_message(): void
    {
        $transport = $this->createTransport();

        $sentMessage = $this->createStub(SentMessage::class);
        $sentMessage->method('getOriginalMessage')->willReturn(new RawMessage(''));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Expected message to be an instance of');

        // doSend is protected on AbstractTransport — reflection required to test directly
        $ref = new ReflectionClass($transport);
        $method = $ref->getMethod('doSend');
        $method->invoke($transport, $sentMessage);
    }

    #[Test]
    public function throws_on_http_error(): void
    {
        Http::fake([
            self::API_URL => fn () => throw new ConnectionException('Connection timed out', 0),
        ]);

        $transport = $this->createTransport();
        $email = $this->basicEmail()->subject('Error')->text('Body');

        $this->expectException(MailtrapTransportException::class);
        $this->expectExceptionMessage('Mailtrap API request failed: Connection timed out');

        $transport->send($email);
    }

    #[Test]
    public function throws_on_non2xx_response(): void
    {
        Http::fake([
            self::API_URL => Http::response(['error' => 'Internal error'], 500),
        ]);

        $transport = $this->createTransport();
        $email = $this->basicEmail()->subject('500 test')->text('Body');

        $this->assertThrowsApiException(500, '{"error":"Internal error"}', function () use ($transport, $email): void {
            $transport->send($email);
        });
    }

    #[Test]
    public function api_exception_message_includes_truncated_body(): void
    {
        Http::fake([
            self::API_URL => Http::response(['error' => 'Something went wrong'], 400),
        ]);

        $transport = $this->createTransport();
        $email = $this->basicEmail()->subject('400 test')->text('Body');

        $this->expectException(MailtrapApiException::class);
        $this->expectExceptionMessageMatches('/HTTP 400.*Something went wrong/');

        $transport->send($email);
    }

    /**
     * @param  array<string, mixed>|string  $body
     */
    #[Test]
    #[DataProvider('extractMessageIdProvider')]
    public function extracts_message_id_from_response(mixed $body, bool $hasMessageId): void
    {
        Http::fake([
            self::API_URL => Http::response($body, 200),
        ]);

        $transport = $this->createTransport();
        $email = $this->basicEmail()->subject('Test')->text('Body');
        $result = $transport->send($email);

        $this->assertNotNull($result);

        if ($hasMessageId) {
            $this->assertStringContainsString('Message ID: msg_abc123', $result->getDebug());
        } else {
            $this->assertStringNotContainsString('Message ID:', (string) $result->getDebug());
        }
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function extractMessageIdProvider(): array
    {
        return [
            'valid JSON with id' => [['success' => true, 'message_ids' => ['msg_abc123']], true],
            'invalid JSON' => ['not json', false],
            'missing message_ids key' => [['success' => true], false],
            'empty message_ids array' => [['success' => true, 'message_ids' => []], false],
        ];
    }

    #[Test]
    public function non2xx_response_with_long_body_preserves_full_body(): void
    {
        Http::fake([
            self::API_URL => Http::response(str_repeat('x', 600), 502),
        ]);

        $transport = $this->createTransport();
        $email = $this->basicEmail()->subject('Long error')->text('Body');

        $this->assertThrowsApiException(502, str_repeat('x', 600), function () use ($transport, $email): void {
            $transport->send($email);
        });
    }

    #[Test]
    public function to_string_includes_endpoint_host(): void
    {
        $transport = $this->createTransport();

        $this->assertSame('mailtrap+api://send.api.mailtrap.io', (string) $transport);
    }

    #[Test]
    public function to_string_with_base_url_override(): void
    {
        $transport = new MailtrapTransport(
            http: app(HttpFactory::class),
            config: new MailtrapConfig(
                token: self::TEST_TOKEN,
                baseUrl: 'https://custom-proxy.example.com',
            ),
        );

        $this->assertSame('mailtrap+api://custom-proxy.example.com', (string) $transport);
    }

    #[Test]
    public function mail_manager_creates_transport_from_config(): void
    {
        config()->set('mailtrap.token', self::TEST_TOKEN);

        $transport = app('mail.manager')
            ->createSymfonyTransport(['transport' => 'mailtrap']);

        $this->assertInstanceOf(MailtrapTransport::class, $transport);
    }

    #[Test]
    public function service_provider_respects_config_key(): void
    {
        config()->set('mailtrap.custom', [
            'token' => 'custom-token',
        ]);

        $transport = app('mail.manager')
            ->createSymfonyTransport([
                'transport' => 'mailtrap',
                'config' => 'mailtrap.custom',
            ]);

        $this->assertInstanceOf(MailtrapTransport::class, $transport);
    }

    #[Test]
    public function service_provider_respects_base_url_config(): void
    {
        config()->set('mailtrap.custom', [
            'token' => 'custom-token',
            'base_url' => 'https://custom.api.mailtrap.io',
        ]);

        $transport = app('mail.manager')
            ->createSymfonyTransport([
                'transport' => 'mailtrap',
                'config' => 'mailtrap.custom',
            ]);

        $this->assertInstanceOf(MailtrapTransport::class, $transport);
        $this->assertStringContainsString('custom.api.mailtrap.io', (string) $transport);
    }

    #[Test]
    public function service_provider_handles_non_array_http_option(): void
    {
        config()->set('mailtrap.token', self::TEST_TOKEN);
        config()->set('mailtrap.http', 'not-an-array');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "http" configuration key must be an array.');

        app('mail.manager')
            ->createSymfonyTransport(['transport' => 'mailtrap']);
    }

    #[Test]
    public function config_file_is_publishable(): void
    {
        $this->assertFileExists(__DIR__.'/../config/mailtrap.php');
    }

    #[Test]
    public function config_is_registered_via_service_provider(): void
    {
        $this->assertIsArray(config('mailtrap.http'));
        $this->assertSame(10, config('mailtrap.http.connect_timeout'));
        $this->assertSame(30, config('mailtrap.http.timeout'));
    }
}
