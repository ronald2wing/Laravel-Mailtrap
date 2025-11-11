<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap\Exceptions;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

final class MailtrapApiException extends MailtrapException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {
        parent::__construct(sprintf(
            'Mailtrap API returned HTTP %d: %s',
            $statusCode,
            Str::limit($body, 200),
        ));
    }

    public static function fromResponse(Response $response): self
    {
        return new self($response->status(), $response->body());
    }
}
