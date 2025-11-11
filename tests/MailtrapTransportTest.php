<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Mockery;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use Ronald2Wing\LaravelMailtrap\MailtrapServiceProvider;
use Ronald2Wing\LaravelMailtrap\MailtrapTransport;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Mailtrap Transport Test Suite
 *
 * Comprehensive tests for the Mailtrap API integration including:
 * - Email sending (text, HTML, multipart)
 * - Recipients (to, cc, bcc)
 * - Attachments (regular, inline)
 * - Headers and metadata
 * - Error handling
 *
 * @covers \Ronald2Wing\LaravelMailtrap\MailtrapServiceProvider
 * @covers \Ronald2Wing\LaravelMailtrap\MailtrapTransport
 */
class MailtrapTransportTest extends TestCase
{
    private const DEFAULT_API_ENDPOINT = 'https://send.api.mailtrap.io/api/send';

    private const TEST_API_TOKEN = 'test-api-token';

    private const SENDER_EMAIL = 'sender@example.com';

    private const SENDER_NAME = 'Test Sender';

    private const RECIPIENT_EMAIL = 'recipient@example.com';

    /**
     * Test transport creation with advanced Guzzle configuration
     *
     * @group transport
     * @group configuration
     */
    public function test_can_create_transport_with_advanced_configuration(): void
    {
        // Configure advanced Guzzle options
        $this->app['config']->set('services.mailtrap', [
            'token' => self::TEST_API_TOKEN,
            'guzzle' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Test-App/1.0',
                ],
            ],
        ]);

        $transport = $this->app['mail.manager']
            ->createSymfonyTransport(['transport' => 'mailtrap']);

        $this->assertInstanceOf(MailtrapTransport::class, $transport);
    }

    /**
     * Test transport creation with minimal configuration
     *
     * @group transport
     * @group configuration
     */
    public function test_can_create_transport_with_minimal_configuration(): void
    {
        // Configure only the required token
        $this->app['config']->set('services.mailtrap', [
            'token' => self::TEST_API_TOKEN,
        ]);

        $transport = $this->app['mail.manager']
            ->createSymfonyTransport(['transport' => 'mailtrap']);

        $this->assertInstanceOf(MailtrapTransport::class, $transport);
    }

    /**
     * Test sending plain text email with category header
     *
     * @group email
     * @group sending
     * @group headers
     */
    public function test_can_send_plain_text_email_with_category(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest, ['message_ids' => [123]]);

        $email = $this->createEmail()
            ->subject('Test Email Subject')
            ->text('This is a plain text email body.');

        $email->getHeaders()->addTextHeader('X-Mailtrap-Category', 'test-category');

        $transport->send($email);

        $this->assertRequestWasSentTo('POST', self::DEFAULT_API_ENDPOINT, $capturedRequest);
        $this->assertPayloadContains($capturedRequest, [
            'headers' => ['Api-Token' => self::TEST_API_TOKEN],
            'json' => [
                'from' => ['email' => self::SENDER_EMAIL, 'name' => self::SENDER_NAME],
                'to' => [['email' => self::RECIPIENT_EMAIL]],
                'subject' => 'Test Email Subject',
                'category' => 'test-category',
                'text' => 'This is a plain text email body.',
            ],
        ]);
        $this->assertPayloadDoesNotContainKey($capturedRequest, 'json.html');
    }

    /**
     * Test sending HTML-only email
     *
     * @group email
     * @group sending
     * @group html
     */
    public function test_can_send_html_only_email(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $htmlContent = '<h1>HTML Email</h1><p>This is an HTML email body.</p>';
        $email = $this->createEmail()
            ->subject('HTML Email Subject')
            ->html($htmlContent);

        $transport->send($email);

        $this->assertPayloadContains($capturedRequest, [
            'json' => [
                'subject' => 'HTML Email Subject',
                'html' => $htmlContent,
            ],
        ]);
        $this->assertPayloadDoesNotContainKey($capturedRequest, 'json.text');
    }

    /**
     * Test sending multipart email (both HTML and plain text)
     */
    public function test_can_send_multipart_email_with_html_and_text(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->subject('Multipart Email Subject')
            ->text('This is the plain text version.')
            ->html('<h1>Multipart Email</h1><p>This is the HTML version.</p>');

        $transport->send($email);

        $this->assertPayloadContains($capturedRequest, [
            'json' => [
                'subject' => 'Multipart Email Subject',
                'text' => 'This is the plain text version.',
                'html' => '<h1>Multipart Email</h1><p>This is the HTML version.</p>',
            ],
        ]);
    }

    /**
     * Test sending email with CC recipients
     */
    public function test_sends_email_with_cc_recipients(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->cc(new Address('cc@example.com', 'CC Recipient'))
            ->subject('Email with CC')
            ->text('This email has CC recipients.');

        $transport->send($email);

        $this->assertPayloadContains($capturedRequest, [
            'json' => [
                'cc' => [['email' => 'cc@example.com', 'name' => 'CC Recipient']],
            ],
        ]);
    }

    /**
     * Test sending email with BCC recipients
     */
    public function test_sends_email_with_bcc_recipients(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->bcc(new Address('bcc@example.com', 'BCC Recipient'))
            ->subject('Email with BCC')
            ->text('This email has BCC recipients.');

        $transport->send($email);

        $this->assertPayloadContains($capturedRequest, [
            'json' => [
                'bcc' => [['email' => 'bcc@example.com', 'name' => 'BCC Recipient']],
            ],
        ]);
    }

    /**
     * Test sending email with multiple recipient types
     */
    public function test_sends_email_with_multiple_recipient_types(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->to(
                new Address('recipient1@example.com', 'Recipient One'),
                new Address('recipient2@example.com', 'Recipient Two')
            )
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Email with Multiple Recipients')
            ->text('This email has multiple recipients.');

        $transport->send($email);

        $payload = $this->extractPayload($capturedRequest);
        $this->assertCount(4, $payload['json']['to']);
        $this->assertCount(1, $payload['json']['cc']);
        $this->assertCount(1, $payload['json']['bcc']);
    }

    /**
     * Test sending email with a single attachment
     */
    public function test_sends_email_with_single_attachment(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $attachmentContent = 'Test attachment content';
        $email = $this->createEmail()
            ->subject('Email with Attachment')
            ->text('This email has an attachment.')
            ->attach($attachmentContent, 'test.txt', 'text/plain');

        $transport->send($email);

        $this->assertPayloadContains($capturedRequest, [
            'json' => [
                'attachments' => [
                    [
                        'filename' => 'test.txt',
                        'type' => 'text/plain',
                        'disposition' => 'attachment',
                        'content' => base64_encode($attachmentContent),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test sending email with multiple attachments
     */
    public function test_sends_email_with_multiple_attachments(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->subject('Email with Multiple Attachments')
            ->text('This email has multiple attachments.')
            ->attach('Content 1', 'test1.txt', 'text/plain')
            ->attach('Content 2', 'test2.pdf', 'application/pdf');

        $transport->send($email);

        $payload = $this->extractPayload($capturedRequest);
        $attachments = $payload['json']['attachments'];

        $this->assertCount(2, $attachments);
        $this->assertEquals('test1.txt', $attachments[0]['filename']);
        $this->assertEquals('text/plain', $attachments[0]['type']);
        $this->assertEquals('test2.pdf', $attachments[1]['filename']);
        $this->assertEquals('application/pdf', $attachments[1]['type']);
    }

    /**
     * Test sending email with inline attachment (embedded image)
     */
    public function test_sends_email_with_inline_attachment(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $inlineContent = 'Test inline image content';
        $email = $this->createEmail()
            ->subject('Email with Inline Attachment')
            ->html('<h1>Email with inline image</h1><img src="cid:logo">')
            ->embed($inlineContent, 'logo', 'image/png');

        $transport->send($email);

        $this->assertPayloadContains($capturedRequest, [
            'json' => [
                'attachments' => [
                    [
                        'filename' => 'logo',
                        'type' => 'image/png',
                        'disposition' => 'inline',
                        'content' => base64_encode($inlineContent),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test sending email with both regular and inline attachments
     */
    public function test_sends_email_with_mixed_attachment_types(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->subject('Email with Mixed Attachments')
            ->html('<h1>Email with attachments</h1><img src="cid:logo">')
            ->attach('Regular attachment', 'document.pdf', 'application/pdf')
            ->embed('Inline image', 'logo', 'image/png');

        $transport->send($email);

        $payload = $this->extractPayload($capturedRequest);
        $attachments = $payload['json']['attachments'];

        $this->assertCount(2, $attachments);

        $regularAttachments = $this->filterAttachmentsByDisposition($attachments, 'attachment');
        $inlineAttachments = $this->filterAttachmentsByDisposition($attachments, 'inline');

        $this->assertCount(1, $regularAttachments);
        $this->assertCount(1, $inlineAttachments);
    }

    /**
     * Test sending email with Reply-To header
     */
    public function test_sends_email_with_reply_to_header(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->replyTo(new Address('reply@example.com', 'Reply Handler'))
            ->subject('Email with Reply-To')
            ->text('This email has a reply-to address.');

        $transport->send($email);

        $payload = $this->extractPayload($capturedRequest);
        $this->assertArrayHasKey('headers', $payload['json']);
    }

    /**
     * Test sending email from sender without display name
     */
    public function test_sends_email_from_sender_without_name(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = new Email;
        $email->from(self::SENDER_EMAIL)
            ->to(new Address(self::RECIPIENT_EMAIL))
            ->subject('Email from Sender Without Name')
            ->text('This email has no sender name.');

        $transport->send($email);

        $this->assertPayloadContains($capturedRequest, [
            'json' => [
                'from' => ['email' => self::SENDER_EMAIL],
            ],
        ]);
    }

    /**
     * Test sending email with UTF-8 characters
     */
    public function test_sends_email_with_international_characters(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = new Email;
        $email->from(new Address(self::SENDER_EMAIL, 'José García & María'))
            ->to(new Address(self::RECIPIENT_EMAIL, 'François Müller'))
            ->subject('Testing UTF-8: 你好 & "quotes" €£¥')
            ->text('Email body with UTF-8: 日本語 Español Français 中文');

        $transport->send($email);

        $this->assertPayloadContains($capturedRequest, [
            'json' => [
                'subject' => 'Testing UTF-8: 你好 & "quotes" €£¥',
                'text' => 'Email body with UTF-8: 日本語 Español Français 中文',
            ],
        ]);
    }

    /**
     * Test sending email with custom headers
     */
    public function test_sends_email_with_custom_headers(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->subject('Email with Custom Headers')
            ->text('This email has custom headers.');

        $email->getHeaders()->addTextHeader('X-Custom-Header', 'custom-value');
        $email->getHeaders()->addTextHeader('X-Priority', '1');

        $transport->send($email);

        $payload = $this->extractPayload($capturedRequest);
        $this->assertArrayHasKey('headers', $payload['json']);
    }

    /**
     * Test sending email to custom API endpoint
     */
    public function test_sends_email_to_custom_api_endpoint(): void
    {
        $customEndpoint = 'custom.api.mailtrap.io';
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient, self::TEST_API_TOKEN, $customEndpoint);
        $capturedRequest = null;

        $this->mockSuccessfulApiCall($mockClient, $capturedRequest);

        $email = $this->createEmail()
            ->subject('Custom Endpoint Email')
            ->text('This email uses a custom endpoint.');

        $transport->send($email);

        $expectedUrl = "https://{$customEndpoint}/api/send";
        $this->assertRequestWasSentTo('POST', $expectedUrl, $capturedRequest);
    }

    /**
     * Test transport string representation
     */
    public function test_transport_string_representation_is_correct(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);

        $this->assertEquals('mailtrap', (string) $transport);
    }

    /**
     * Test error handling for API request failures
     */
    public function test_throws_exception_on_api_request_failure(): void
    {
        $mockClient = $this->createMockHttpClient();
        $transport = $this->createTransport($mockClient);

        $mockClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(new RequestException(
                'API Error',
                new Request('POST', self::DEFAULT_API_ENDPOINT)
            ));

        $email = $this->createEmail()
            ->subject('Test Email')
            ->text('Test content');

        $this->expectException(RequestException::class);

        $transport->send($email);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Create a mock HTTP client for testing
     *
     * @return ClientInterface&Mockery\MockInterface
     */
    private function createMockHttpClient(): ClientInterface
    {
        return Mockery::mock(ClientInterface::class);
    }

    /**
     * Create a Mailtrap transport instance for testing
     *
     * @param  ClientInterface  $httpClient  Mock HTTP client
     * @param  string  $apiToken  API token (defaults to test token)
     * @param  string|null  $endpoint  Custom API endpoint (optional)
     * @return MailtrapTransport Configured transport instance
     */
    private function createTransport(
        ClientInterface $httpClient,
        string $apiToken = self::TEST_API_TOKEN,
        ?string $endpoint = null
    ): MailtrapTransport {
        return new MailtrapTransport($httpClient, $apiToken, $endpoint);
    }

    /**
     * Create a basic email for testing
     */
    private function createEmail(): Email
    {
        $email = new Email;
        $email->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to(new Address(self::RECIPIENT_EMAIL));

        return $email;
    }

    /**
     * Mock a successful API call and capture the request
     *
     * @param  array<string, mixed>|null  $capturedRequest
     * @param  array<string, mixed>  $responseData
     */
    private function mockSuccessfulApiCall(
        ClientInterface $mockClient,
        ?array &$capturedRequest,
        array $responseData = []
    ): void {
        $defaultResponse = ['success' => true, 'message_ids' => [rand(100, 999)]];
        $responseData = array_merge($defaultResponse, $responseData);

        $mockClient
            ->shouldReceive('request')
            ->once()
            ->andReturnUsing(function (...$parameters) use (&$capturedRequest, $responseData) {
                $capturedRequest = [
                    'method' => $parameters[0],
                    'url' => $parameters[1],
                    'payload' => $parameters[2],
                ];

                return $this->createMockResponse($responseData);
            });
    }

    /**
     * Create a mock HTTP response
     */
    private function createMockResponse(array $data): ResponseInterface
    {
        $body = Mockery::mock(StreamInterface::class);
        $body->shouldReceive('getContents')->andReturn(json_encode($data));

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn($body);

        return $response;
    }

    /**
     * Assert that a request was sent to a specific endpoint
     */
    private function assertRequestWasSentTo(string $method, string $url, ?array $capturedRequest): void
    {
        $this->assertNotNull($capturedRequest, 'No request was captured');
        $this->assertEquals($method, $capturedRequest['method']);
        $this->assertEquals($url, $capturedRequest['url']);
    }

    /**
     * Extract payload from captured request
     */
    private function extractPayload(?array $capturedRequest): array
    {
        $this->assertNotNull($capturedRequest, 'No request was captured');

        return $capturedRequest['payload'];
    }

    /**
     * Assert that payload contains specific data
     */
    private function assertPayloadContains(?array $capturedRequest, array $expectedData): void
    {
        $payload = $this->extractPayload($capturedRequest);

        foreach ($expectedData as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $actualValue = $payload[$key][$subKey] ?? null;
                    $this->assertEquals(
                        $subValue,
                        $actualValue,
                        "Payload mismatch at {$key}.{$subKey}"
                    );
                }
            } else {
                $this->assertEquals($value, $payload[$key], "Payload mismatch at {$key}");
            }
        }
    }

    /**
     * Assert that payload does not contain a specific key
     */
    private function assertPayloadDoesNotContainKey(?array $capturedRequest, string $keyPath): void
    {
        $payload = $this->extractPayload($capturedRequest);
        $keys = explode('.', $keyPath);
        $current = $payload;

        foreach ($keys as $key) {
            if (! isset($current[$key])) {
                $this->assertTrue(true);

                return;
            }
            $current = $current[$key];
        }

        $this->fail("Payload should not contain key: {$keyPath}");
    }

    /**
     * Filter attachments by disposition type
     */
    private function filterAttachmentsByDisposition(array $attachments, string $disposition): array
    {
        return array_filter($attachments, fn ($att) => $att['disposition'] === $disposition);
    }

    // ========================================================================
    // TestCase Configuration
    // ========================================================================

    /**
     * Configure the test environment
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('mail.default', 'mailtrap');
        $app['config']->set('mail.mailers.mailtrap', [
            'transport' => 'mailtrap',
        ]);
        $app['config']->set('mail.from', [
            'address' => self::SENDER_EMAIL,
            'name' => self::SENDER_NAME,
        ]);
        $app['config']->set('services.mailtrap', [
            'token' => self::TEST_API_TOKEN,
        ]);
    }

    /**
     * Get package providers for testing
     */
    protected function getPackageProviders($app): array
    {
        return [
            MailtrapServiceProvider::class,
        ];
    }

    // ========================================================================
    // Additional Tests for Uncovered Code
    // ========================================================================

    /**
     * Test that MailtrapServiceProvider throws exception when token is missing
     */
    public function test_throws_exception_when_token_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mailtrap API token is missing');

        $app = $this->app;
        $app['config']->set('services.mailtrap', []);

        $provider = new MailtrapServiceProvider($app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('validateConfiguration');
        $method->setAccessible(true);
        $method->invoke($provider, []);
    }

    /**
     * Test that MailtrapServiceProvider throws exception when token is not a string
     */
    public function test_throws_exception_when_token_is_not_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mailtrap API token must be a string');

        $app = $this->app;
        $app['config']->set('services.mailtrap', []);

        $provider = new MailtrapServiceProvider($app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('validateConfiguration');
        $method->setAccessible(true);
        $method->invoke($provider, ['token' => 123]);
    }

    /**
     * Test that MailtrapServiceProvider handles non-array guzzle options
     */
    public function test_handles_non_array_guzzle_options(): void
    {
        $app = $this->app;
        $app['config']->set('services.mailtrap', [
            'token' => self::TEST_API_TOKEN,
            'guzzle' => 'not-an-array',
        ]);

        $provider = new MailtrapServiceProvider($app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('createHttpClient');
        $method->setAccessible(true);

        $client = $method->invoke($provider, [
            'token' => self::TEST_API_TOKEN,
            'guzzle' => 'not-an-array',
        ]);

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    /**
     * Test MailtrapTransport setter methods
     */
    public function test_setter_methods_work_correctly(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $transport = new MailtrapTransport($httpClient, self::TEST_API_TOKEN);

        // Test setHttpClient
        $newHttpClient = $this->createMock(ClientInterface::class);
        $result = $transport->setHttpClient($newHttpClient);
        $this->assertSame($transport, $result);

        // Test setApiToken
        $newToken = 'new-test-token';
        $result = $transport->setApiToken($newToken);
        $this->assertSame($transport, $result);

        // Test setApiEndpoint
        $newEndpoint = 'https://new-api.example.com';
        $result = $transport->setApiEndpoint($newEndpoint);
        $this->assertSame($transport, $result);
    }

    /**
     * Test extractMessageId with invalid JSON response
     */
    public function test_extract_message_id_with_invalid_json(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $transport = new MailtrapTransport($httpClient, self::TEST_API_TOKEN);

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('extractMessageId');
        $method->setAccessible(true);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('invalid json');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        $result = $method->invoke($transport, $response);
        $this->assertNull($result);
    }

    /**
     * Test extractMessageId with JSON missing message_ids
     */
    public function test_extract_message_id_without_message_ids(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $transport = new MailtrapTransport($httpClient, self::TEST_API_TOKEN);

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('extractMessageId');
        $method->setAccessible(true);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['success' => true]));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        $result = $method->invoke($transport, $response);
        $this->assertNull($result);
    }

    /**
     * Test extractMessageId with empty message_ids array
     */
    public function test_extract_message_id_with_empty_message_ids(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $transport = new MailtrapTransport($httpClient, self::TEST_API_TOKEN);

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('extractMessageId');
        $method->setAccessible(true);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['success' => true, 'message_ids' => []]));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        $result = $method->invoke($transport, $response);
        $this->assertNull($result);
    }

    /**
     * Test that doSend throws an exception when the message is not an instance of Email
     */
    public function test_throws_exception_when_message_is_not_email_instance(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $transport = new MailtrapTransport($httpClient, self::TEST_API_TOKEN);

        // Create a mock SentMessage that returns a RawMessage (not an Email)
        $sentMessage = $this->createMock(SentMessage::class);
        $rawMessage = $this->createMock(RawMessage::class);
        $sentMessage->method('getOriginalMessage')->willReturn($rawMessage);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The message must be an instance of '.Email::class);

        // Use reflection to call the protected doSend method
        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('doSend');
        $method->setAccessible(true);
        $method->invoke($transport, $sentMessage);
    }
}
