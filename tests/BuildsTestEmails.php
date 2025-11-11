<?php

declare(strict_types=1);

namespace Ronald2Wing\LaravelMailtrap\Tests;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

trait BuildsTestEmails
{
    protected const SENDER_EMAIL = 'sender@example.com';

    protected const SENDER_NAME = 'Test Sender';

    protected const RECIPIENT_EMAIL = 'recipient@example.com';

    protected const TEST_TOKEN = 'test-api-token';

    protected const API_URL = 'https://send.api.mailtrap.io/api/send';

    private function basicEmail(): Email
    {
        return (new Email)
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to(new Address(self::RECIPIENT_EMAIL));
    }
}
