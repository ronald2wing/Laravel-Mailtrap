<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final class MessagePayloadFactory
{
    /**
     * @var array<string, array{key: string, json: bool}>
     */
    private const DIRECTIVES = [
        'X-Mailtrap-Category' => ['key' => 'category',           'json' => false],
        'X-Mailtrap-Template-Uuid' => ['key' => 'template_uuid',      'json' => false],
        'X-Mailtrap-Template-Variables' => ['key' => 'template_variables', 'json' => true],
        'X-Mailtrap-Custom-Variables' => ['key' => 'custom_variables',   'json' => true],
    ];

    /**
     * @var list<string>
     */
    private const RESERVED_HEADERS = [
        'reply-to',
        'subject',
        'from',
        'to',
        'cc',
        'bcc',
        'date',
        'message-id',
        'mime-version',
        'content-type',
        'content-transfer-encoding',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(Email $email, Envelope $envelope): array
    {
        return array_filter([
            'from' => $this->formatAddress($envelope->getSender()),
            'to' => array_map($this->formatAddress(...), $envelope->getRecipients()),
            'cc' => array_map($this->formatAddress(...), $email->getCc()),
            'bcc' => array_map($this->formatAddress(...), $email->getBcc()),
            'subject' => $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'attachments' => array_map($this->formatAttachment(...), $email->getAttachments()),
            ...$this->extractDirectives($email),
            'headers' => $this->extractCustomHeaders($email),
        ], self::hasValue(...));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDirectives(Email $email): array
    {
        $result = [];

        foreach (self::DIRECTIVES as $headerName => $mapping) {
            $header = $email->getHeaders()->get($headerName);

            if ($header === null) {
                continue;
            }

            $value = $header->getBodyAsString();

            if (! $mapping['json']) {
                $result[$mapping['key']] = $value;

                continue;
            }

            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $result[$mapping['key']] = $decoded;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function extractCustomHeaders(Email $email): array
    {
        $headers = [];
        $reserved = $this->reservedHeaderNames();

        $replyTo = $email->getReplyTo();

        if ($replyTo !== []) {
            $headers['Reply-To'] = implode(', ', array_map(
                fn (Address $address): string => $address->toString(),
                $replyTo,
            ));
        }

        foreach ($email->getHeaders()->all() as $header) {
            $name = $header->getName();

            if (! in_array(strtolower($name), $reserved, true)) {
                $headers[$name] = $header->getBodyAsString();
            }
        }

        return $headers;
    }

    /**
     * @return list<string>
     */
    private function reservedHeaderNames(): array
    {
        return [
            ...self::RESERVED_HEADERS,
            ...array_map('strtolower', array_keys(self::DIRECTIVES)),
        ];
    }

    private static function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== [];
    }

    /**
     * @return array{email: string, name?: string}
     */
    private function formatAddress(Address $address): array
    {
        $formatted = ['email' => $address->getAddress()];

        if ($name = $address->getName()) {
            $formatted['name'] = $name;
        }

        return $formatted;
    }

    /**
     * @return array{content: string, type: string, filename: string|null, disposition: string|null}
     */
    private function formatAttachment(DataPart $attachment): array
    {
        return [
            'content' => base64_encode($attachment->getBody()),
            'type' => sprintf('%s/%s', $attachment->getMediaType(), $attachment->getMediaSubtype()),
            'filename' => $attachment->getFilename(),
            'disposition' => $attachment->getDisposition(),
        ];
    }
}
