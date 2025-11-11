<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Mailtrap API Transport for Laravel
 *
 * Implements Symfony Mailer transport for sending emails through the
 * Mailtrap.io Email Sending Service API.
 *
 * This transport handles:
 * - Text, HTML, and multipart emails
 * - Attachments (regular and inline)
 * - Recipients (To, CC, BCC)
 * - Custom headers and Mailtrap categories
 * - UTF-8 international character support
 *
 * @author Ronald Wong <ronald2wing@gmail.com>
 * @license MIT https://opensource.org/licenses/MIT
 *
 * @link   https://github.com/ronald2wing/laravel-mailtrap
 */
class MailtrapTransport extends AbstractTransport
{
    /** Default Mailtrap API endpoint */
    private const DEFAULT_API_ENDPOINT = 'send.api.mailtrap.io';

    /** Headers to exclude from custom header forwarding */
    private const EXCLUDED_HEADERS = [
        'X-Mailtrap-Category',
        'Reply-To',
        'Subject',
        'From',
        'To',
        'Cc',
        'Bcc',
        'Date',
        'Message-ID',
        'MIME-Version',
        'Content-Type',
    ];

    /** HTTP client for API communication */
    protected ClientInterface $httpClient;

    /** Mailtrap API authentication token */
    protected string $apiToken;

    /** Mailtrap API endpoint base URL */
    protected string $apiEndpoint;

    /**
     * Create a new Mailtrap API transport instance
     *
     * @param  ClientInterface  $httpClient  HTTP client for API requests
     * @param  string  $apiToken  Mailtrap API authentication token
     * @param  string|null  $apiEndpoint  Custom API endpoint (optional)
     */
    public function __construct(
        ClientInterface $httpClient,
        string $apiToken,
        ?string $apiEndpoint = null
    ) {
        $this->httpClient = $httpClient;
        $this->apiToken = $apiToken;
        $this->apiEndpoint = $apiEndpoint ?? self::DEFAULT_API_ENDPOINT;

        parent::__construct();
    }

    /**
     * Send the email through Mailtrap API
     *
     * @param  SentMessage  $message  The message to send
     *
     * @throws RuntimeException If the message is not an instance of Email
     */
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new RuntimeException(
                'The message must be an instance of '.Email::class
            );
        }

        $envelope = $message->getEnvelope();
        $payload = $this->buildApiPayload($email, $envelope);
        $response = $this->sendMailtrapApiRequest($payload);

        $this->appendMessageIdToDebug($message, $response);
    }

    /**
     * Send the email payload to Mailtrap API
     *
     * @param  array<string, mixed>  $payload  Email data payload
     * @return ResponseInterface API response
     */
    protected function sendMailtrapApiRequest(array $payload): ResponseInterface
    {
        return $this->httpClient->request(
            'POST',
            'https://'.$this->apiEndpoint.'/api/send',
            $payload
        );
    }

    /**
     * Append message ID to debug information
     *
     * @param  SentMessage  $message  The sent message
     * @param  ResponseInterface  $response  API response
     */
    protected function appendMessageIdToDebug(
        SentMessage $message,
        ResponseInterface $response
    ): void {
        $messageId = $this->extractMessageId($response);

        if ($messageId !== null) {
            $message->appendDebug("Message ID: {$messageId}");
        }
    }

    /**
     * Build the API payload for Mailtrap
     *
     * @param  Email  $email  The email to send
     * @param  Envelope  $envelope  The email envelope
     * @return array<string, mixed> API request payload
     */
    protected function buildApiPayload(Email $email, Envelope $envelope): array
    {
        $payload = $this->createBasePayload($email, $envelope);

        $this->addRecipientsToPayload($email, $payload);
        $this->addContentToPayload($email, $payload);
        $this->addAttachmentsToPayload($email, $payload);
        $this->addMetadataToPayload($email, $payload);

        return $payload;
    }

    /**
     * Create the base payload structure
     *
     * @param  Email  $email  The email to send
     * @param  Envelope  $envelope  The email envelope
     * @return array<string, mixed> Base payload structure
     */
    protected function createBasePayload(Email $email, Envelope $envelope): array
    {
        return [
            'headers' => [
                'Api-Token' => $this->apiToken,
            ],
            'json' => [
                'from' => $this->formatAddress($envelope->getSender()),
                'to' => array_map([$this, 'formatAddress'], $envelope->getRecipients()),
                'subject' => $email->getSubject(),
            ],
        ];
    }

    /**
     * Add all recipient types to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addRecipientsToPayload(Email $email, array &$payload): void
    {
        $this->addCcRecipients($email, $payload);
        $this->addBccRecipients($email, $payload);
    }

    /**
     * Add email content to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addContentToPayload(Email $email, array &$payload): void
    {
        $this->addEmailContent($email, $payload);
    }

    /**
     * Add attachments to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addAttachmentsToPayload(Email $email, array &$payload): void
    {
        $this->addEmailAttachments($email, $payload);
    }

    /**
     * Add metadata (category, headers, reply-to) to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addMetadataToPayload(Email $email, array &$payload): void
    {
        $this->addMailtrapCategory($email, $payload);
        $this->addCustomEmailHeaders($email, $payload);
        $this->addReplyToAddress($email, $payload);
    }

    /**
     * Add CC recipients to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addCcRecipients(Email $email, array &$payload): void
    {
        $cc = $email->getCc();

        if (! empty($cc)) {
            $payload['json']['cc'] = array_map([$this, 'formatAddress'], $cc);
        }
    }

    /**
     * Add BCC recipients to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addBccRecipients(Email $email, array &$payload): void
    {
        $bcc = $email->getBcc();

        if (! empty($bcc)) {
            $payload['json']['bcc'] = array_map([$this, 'formatAddress'], $bcc);
        }
    }

    /**
     * Add email content to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addEmailContent(Email $email, array &$payload): void
    {
        $htmlBody = $email->getHtmlBody();
        if ($htmlBody !== null) {
            $payload['json']['html'] = $htmlBody;
        }

        $textBody = $email->getTextBody();
        if ($textBody !== null) {
            $payload['json']['text'] = $textBody;
        }
    }

    /**
     * Add email attachments to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addEmailAttachments(Email $email, array &$payload): void
    {
        $attachments = $email->getAttachments();

        if (count($attachments) > 0) {
            $payload['json']['attachments'] = array_map(
                [$this, 'formatAttachment'],
                $attachments
            );
        }
    }

    /**
     * Add Mailtrap category to the payload if available
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addMailtrapCategory(Email $email, array &$payload): void
    {
        $headers = $email->getHeaders();

        if ($headers->has('X-Mailtrap-Category')) {
            $categoryHeader = $headers->get('X-Mailtrap-Category');
            if ($categoryHeader !== null) {
                $payload['json']['category'] = $categoryHeader->getBodyAsString();
                $headers->remove('X-Mailtrap-Category');
            }
        }
    }

    /**
     * Add custom email headers to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addCustomEmailHeaders(Email $email, array &$payload): void
    {
        $customHeaders = $this->extractCustomHeaders($email);

        if (! empty($customHeaders)) {
            $payload['json']['headers'] = $customHeaders;
        }
    }

    /**
     * Add reply-to address to the payload
     *
     * @param  Email  $email  The email
     * @param  array<string, mixed>  $payload  The payload to modify
     */
    protected function addReplyToAddress(Email $email, array &$payload): void
    {
        $replyTo = $email->getReplyTo();

        if (! empty($replyTo)) {
            $replyToAddress = $replyTo[0];
            $payload['json']['headers']['Reply-To'] = $replyToAddress->toString();
        }
    }

    /**
     * Extract custom headers from the email
     *
     * @param  Email  $email  The email
     * @return array<string, string> Custom headers array
     */
    protected function extractCustomHeaders(Email $email): array
    {
        $customHeaders = [];

        foreach ($email->getHeaders()->all() as $header) {
            if ($this->shouldIncludeHeader($header->getName())) {
                $customHeaders[$header->getName()] = $header->getBodyAsString();
            }
        }

        return $customHeaders;
    }

    /**
     * Determine if a header should be included in custom headers
     *
     * @param  string  $headerName  Header name to check
     * @return bool True if header should be included
     */
    protected function shouldIncludeHeader(string $headerName): bool
    {
        return ! in_array($headerName, self::EXCLUDED_HEADERS, true);
    }

    /**
     * Format an address for Mailtrap API
     *
     * @param  Address  $address  The address to format
     * @return array<string, string> Formatted address
     */
    protected function formatAddress(Address $address): array
    {
        $result = [
            'email' => $address->getAddress(),
        ];

        $name = $address->getName();
        if ($name !== '') {
            $result['name'] = $name;
        }

        return $result;
    }

    /**
     * Format an attachment for Mailtrap API
     *
     * @param  DataPart  $attachment  The attachment to format
     * @return array<string, string|null> Formatted attachment
     */
    protected function formatAttachment(DataPart $attachment): array
    {
        return [
            'content' => base64_encode($attachment->getBody()),
            'type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
            'filename' => $attachment->getFilename(),
            'disposition' => $attachment->getDisposition(),
        ];
    }

    /**
     * Extract message ID from API response
     *
     * @param  ResponseInterface  $response  API response
     * @return string|null Message ID or null if not found
     */
    protected function extractMessageId(ResponseInterface $response): ?string
    {
        $responseBody = $response->getBody()->getContents();
        $data = json_decode($responseBody, true);

        if (! is_array($data) || ! isset($data['message_ids'][0])) {
            return null;
        }

        return (string) $data['message_ids'][0];
    }

    /**
     * Get the string representation of the transport
     *
     * @return string Transport identifier
     */
    public function __toString(): string
    {
        return 'mailtrap';
    }

    /**
     * Set the HTTP client instance
     *
     * @param  ClientInterface  $httpClient  HTTP client
     */
    public function setHttpClient(ClientInterface $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * Set the API token
     *
     * @param  string  $apiToken  Mailtrap API token
     */
    public function setApiToken(string $apiToken): self
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    /**
     * Set the API endpoint
     *
     * @param  string  $apiEndpoint  API endpoint
     */
    public function setApiEndpoint(string $apiEndpoint): self
    {
        $this->apiEndpoint = $apiEndpoint;

        return $this;
    }
}
