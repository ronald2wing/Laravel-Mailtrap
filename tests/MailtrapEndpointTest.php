<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ronald2Wing\LaravelMailtrap\MailtrapEndpoint;

final class MailtrapEndpointTest extends TestCase
{
    #[Test]
    public function transactional_does_not_require_inbox_id(): void
    {
        $this->assertFalse(MailtrapEndpoint::Transactional->requiresInboxId());
    }

    #[Test]
    public function bulk_does_not_require_inbox_id(): void
    {
        $this->assertFalse(MailtrapEndpoint::Bulk->requiresInboxId());
    }

    #[Test]
    public function sandbox_requires_inbox_id(): void
    {
        $this->assertTrue(MailtrapEndpoint::Sandbox->requiresInboxId());
    }

    #[Test]
    public function transactional_base_url(): void
    {
        $this->assertSame(
            'https://send.api.mailtrap.io',
            MailtrapEndpoint::Transactional->baseUrl(),
        );
    }

    #[Test]
    public function bulk_base_url(): void
    {
        $this->assertSame(
            'https://bulk.api.mailtrap.io',
            MailtrapEndpoint::Bulk->baseUrl(),
        );
    }

    #[Test]
    public function sandbox_base_url(): void
    {
        $this->assertSame(
            'https://sandbox.api.mailtrap.io',
            MailtrapEndpoint::Sandbox->baseUrl(),
        );
    }
}
