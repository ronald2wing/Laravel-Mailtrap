<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap\Exceptions;

use Illuminate\Http\Client\ConnectionException;

final class MailtrapTransportException extends MailtrapException
{
    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function fromConnectionError(ConnectionException $e): self
    {
        return new self("Mailtrap API request failed: {$e->getMessage()}", $e);
    }
}
