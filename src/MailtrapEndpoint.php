<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap;

enum MailtrapEndpoint: string
{
    case Transactional = 'transactional';
    case Bulk = 'bulk';
    case Sandbox = 'sandbox';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Transactional => 'https://send.api.mailtrap.io',
            self::Bulk => 'https://bulk.api.mailtrap.io',
            self::Sandbox => 'https://sandbox.api.mailtrap.io',
        };
    }

    public function requiresInboxId(): bool
    {
        return $this === self::Sandbox;
    }
}
