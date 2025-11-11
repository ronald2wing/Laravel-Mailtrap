<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Ronald2Wing\LaravelMailtrap\Exceptions\MailtrapApiException;
use Ronald2Wing\LaravelMailtrap\Exceptions\MailtrapTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

final class MailtrapTransport extends AbstractTransport
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly MailtrapConfig $config,
        private readonly MessagePayloadFactory $payloadFactory = new MessagePayloadFactory,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        $host = parse_url($this->config->apiUrl(), PHP_URL_HOST);

        return 'mailtrap+api://'.(is_string($host) ? $host : 'unknown');
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException(sprintf(
                'Expected message to be an instance of %s, got %s.',
                Email::class,
                $email::class,
            ));
        }

        $response = $this->post(
            $this->payloadFactory->build($email, $message->getEnvelope()),
        );

        if (! $response->successful()) {
            throw MailtrapApiException::fromResponse($response);
        }

        $messageId = $this->firstMessageId($response);

        if ($messageId !== null) {
            $message->appendDebug("Message ID: {$messageId}");
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function post(array $json): Response
    {
        try {
            return $this->http
                ->withHeaders(['Api-Token' => $this->config->token])
                ->withOptions($this->config->httpOptions)
                ->post($this->config->apiUrl(), $json);
        } catch (ConnectionException $e) {
            throw MailtrapTransportException::fromConnectionError($e);
        }
    }

    private function firstMessageId(Response $response): ?string
    {
        $data = $response->json();

        if (! is_array($data)) {
            return null;
        }

        if (! isset($data['message_ids']) || ! is_array($data['message_ids'])) {
            return null;
        }

        $first = $data['message_ids'][0] ?? null;

        return is_string($first) && $first !== '' ? $first : null;
    }
}
