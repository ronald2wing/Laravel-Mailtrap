<?php

declare(strict_types=1);

use Ronald2Wing\LaravelMailtrap\MailtrapEndpoint;

return [
    'token' => env('MAILTRAP_TOKEN'),

    'endpoint' => env('MAILTRAP_ENDPOINT', MailtrapEndpoint::Transactional->value),

    'inbox_id' => ($inboxId = env('MAILTRAP_INBOX_ID')) !== null ? (int) $inboxId : null,

    'base_url' => env('MAILTRAP_BASE_URL'),

    'http' => [
        'connect_timeout' => 10,
        'timeout' => 30,
    ],
];
