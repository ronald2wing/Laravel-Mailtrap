# Laravel Mailtrap Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![Total Downloads](https://img.shields.io/packagist/dt/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![PHP Version](https://img.shields.io/packagist/php-v/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![License](https://img.shields.io/packagist/l/ronald2wing/laravel-mailtrap.svg?style=flat-square)](LICENSE)
[![GitHub Actions](https://img.shields.io/github/actions/workflow/status/ronald2wing/laravel-mailtrap/php.yml?branch=master&style=flat-square)](https://github.com/ronald2wing/Laravel-Mailtrap/actions)
[![Codecov](https://img.shields.io/codecov/c/github/ronald2wing/laravel-mailtrap?style=flat-square)](https://codecov.io/gh/ronald2wing/laravel-mailtrap)

A Laravel mail transport for the [Mailtrap Email Sending](https://mailtrap.io/email-sending) API. Adds a `mailtrap` transport that works with Laravel's native `Mail` facade — supporting attachments, CC/BCC, Reply-To, categories, templates, custom headers, custom variables, HTML/plain-text, and multiple accounts.

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Environment variables](#environment-variables)
  - [Laravel mail config](#laravel-mail-config)
  - [Config reference](#config-reference)
- [Usage](#usage)
  - [Categories](#categories)
  - [Templates](#templates)
  - [Attachments](#attachments)
  - [CC, BCC, Reply-To](#cc-bcc-reply-to)
  - [Custom headers](#custom-headers)
  - [HTML and plain text](#html-and-plain-text)
- [Endpoints](#endpoints)
  - [Transactional (default)](#transactional-default)
  - [Bulk](#bulk)
  - [Sandbox (Email Testing)](#sandbox-email-testing)
- [Custom base URL](#custom-base-url)
- [HTTP options](#http-options)
- [Multiple accounts](#multiple-accounts)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

## Requirements

- PHP 8.3+
- Laravel 10, 11, 12, or 13
- PHP extension: curl (required by Guzzle/Laravel HTTP client)

## Installation

```bash
composer require ronald2wing/laravel-mailtrap
```

The service provider auto-registers via Laravel package discovery — no manual setup needed.

Publish the config file:

```bash
php artisan vendor:publish --tag=mailtrap-config
```

## Configuration

### Environment variables

| Variable                | Required          | Default           | Description                                          |
| ----------------------- | ----------------- | ----------------- | ---------------------------------------------------- |
| `MAILTRAP_TOKEN`        | Yes               | —                 | Your Mailtrap API token                              |
| `MAILTRAP_ENDPOINT`     | No                | `transactional`   | `transactional`, `bulk`, or `sandbox`                |
| `MAILTRAP_INBOX_ID`     | With `sandbox`    | —                 | Inbox ID for Email Testing. Must be a positive integer. |
| `MAILTRAP_BASE_URL`     | No                | (endpoint default)| Override the API URL for proxies or staging.         |

### Laravel mail config

Set the default mailer and register the transport in `config/mail.php`:

```php
'default' => env('MAIL_MAILER', 'mailtrap'),

'mailers' => [
    'mailtrap' => [
        'transport' => 'mailtrap',
    ],
],
```

### Config reference

```php
// config/mailtrap.php
use Ronald2Wing\LaravelMailtrap\MailtrapEndpoint;

return [
    'token'    => env('MAILTRAP_TOKEN'),

    'endpoint' => env('MAILTRAP_ENDPOINT', MailtrapEndpoint::Transactional->value),

    'inbox_id' => ($inboxId = env('MAILTRAP_INBOX_ID')) !== null
                   ? (int) $inboxId
                   : null,

    'base_url' => env('MAILTRAP_BASE_URL'),

    'http'     => [
        'connect_timeout' => 10,
        'timeout'         => 30,
    ],
];
```

## Usage

Send mail with the `Mail` facade — identical to any other Laravel driver:

```php
use Illuminate\Support\Facades\Mail;

Mail::to('user@example.com')->send(new WelcomeEmail());
```

### Categories

Add an `X-Mailtrap-Category` header to segment analytics in Mailtrap. Multiple categories are comma-separated.

```php
class WelcomeEmail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Mailtrap-Category' => 'onboarding',
            ],
        );
    }
}
```

Comma-separated for multiple:

```php
public function headers(): Headers
{
    return new Headers(
        text: ['X-Mailtrap-Category' => 'newsletter,marketing,weekly'],
    );
}
```

### Templates

Send through Mailtrap templates using custom headers:

```php
class WelcomeEmail extends Mailable
{
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Mailtrap-Template-Uuid'      => 'abc-123-def',
                'X-Mailtrap-Template-Variables' => json_encode([
                    'name'    => 'John',
                    'company' => 'Acme',
                ]),
                'X-Mailtrap-Custom-Variables'   => json_encode([
                    'plan' => 'pro',
                ]),
            ],
        );
    }
}
```

- `X-Mailtrap-Template-Variables` — values injected into the template. Must be valid JSON.
- `X-Mailtrap-Custom-Variables` — arbitrary key-value data attached to the send, also JSON.

### Attachments

```php
class InvoiceEmail extends Mailable
{
    public function attachments(): array
    {
        return [
            Attachment::fromPath('/path/to/invoice.pdf')
                ->as('invoice.pdf')
                ->withMime('application/pdf'),
            Attachment::fromPath('/path/to/terms.pdf'),
        ];
    }
}
```

Embedded (inline) images are also supported:

```php
$cid = $this->embed('/path/to/logo.png');

return $this->view('emails.newsletter')
    ->with('logoCid', $cid);
```

### CC, BCC, Reply-To

```php
Mail::send('emails.announcement', $data, function ($message) {
    $message->to('team@example.com')
        ->cc(['manager@example.com', 'lead@example.com'])
        ->bcc('archive@example.com')
        ->replyTo('noreply@example.com', 'No Reply')
        ->subject('Announcement');
});
```

Multiple Reply-To addresses are joined with `,` per RFC 5322.

### Custom headers

Arbitrary headers are forwarded in the payload:

```php
Mail::send('emails.order', $data, function ($message) {
    $message->to('customer@example.com')
        ->header('X-Priority', '1')
        ->header('X-Order-ID', 'ORD-12345')
        ->subject('Order Confirmation');
});
```

### HTML and plain text

Provide both HTML and plain-text versions:

```php
class WelcomeEmail extends Mailable
{
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',       // HTML
            text: 'emails.welcome-text',  // plain-text fallback
        );
    }
}
```

## Endpoints

The `endpoint` config key determines which Mailtrap API is used.

### Transactional (default)

One-off emails — notifications, password resets, receipts.

```php
'endpoint' => 'transactional',
```

API: `https://send.api.mailtrap.io/api/send`

### Bulk

High-volume sending via the bulk API.

```php
'endpoint' => 'bulk',
```

API: `https://bulk.api.mailtrap.io/api/send`

### Sandbox (Email Testing)

Test emails within Mailtrap's Email Testing product. Requires an `inbox_id`.

```php
'endpoint'  => 'sandbox',
'inbox_id'  => env('MAILTRAP_INBOX_ID'),
```

API: `https://sandbox.api.mailtrap.io/api/send/{inbox_id}`

## Custom base URL

Override the API URL for proxy environments or staging setups. The `endpoint` still controls authentication behavior — `base_url` only changes the destination:

```php
'base_url' => 'https://custom-proxy.example.com',
```

## HTTP options

Any Guzzle-compatible request option can be set under the `http` key. Defaults are `connect_timeout: 10` and `timeout: 30`.

```php
'http' => [
    'connect_timeout' => 10,
    'timeout'         => 30,
    'verify'          => false,          // disable SSL verification (dev only)
    'proxy'           => 'http://proxy:8080',
],
```

## Multiple accounts

Define sub-configurations in `config/mailtrap.php` and reference them via dot-notation from mailers. Sub-configs inherit top-level defaults (endpoint, base_url, HTTP options) and override only the keys you specify:

```php
// config/mailtrap.php
return [
    'token'         => env('MAILTRAP_TOKEN'),
    'endpoint'      => env('MAILTRAP_ENDPOINT', MailtrapEndpoint::Transactional->value),

    'transactions' => [
        'token'    => env('MAILTRAP_TOKEN_TRANSACTIONS'),
        'endpoint' => 'transactional',
        'http'     => ['timeout' => 60],
    ],

    'marketing' => [
        'token'    => env('MAILTRAP_TOKEN_MARKETING'),
        'endpoint' => 'bulk',
    ],

    'sandbox' => [
        'token'    => env('MAILTRAP_TOKEN_SANDBOX'),
        'endpoint' => 'sandbox',
        'inbox_id' => (int) env('MAILTRAP_INBOX_ID'),
    ],
];
```

```php
// config/mail.php
'mailers' => [
    'mailtrap_transactions' => [
        'transport' => 'mailtrap',
        'config'    => 'mailtrap.transactions',
    ],

    'mailtrap_marketing' => [
        'transport' => 'mailtrap',
        'config'    => 'mailtrap.marketing',
    ],

    'mailtrap_sandbox' => [
        'transport' => 'mailtrap',
        'config'    => 'mailtrap.sandbox',
    ],
];
```

## Testing

Use `Mail::fake()` as usual — no transport swap is needed.

```php
Mail::fake();

// exercise your application...

Mail::assertSent(WelcomeEmail::class, function ($mail) use ($user) {
    return $mail->hasTo($user->email)
        && $mail->hasHeader('X-Mailtrap-Category', 'onboarding');
});
```

## Troubleshooting

| Error                                      | Solution                                                                          |
| ------------------------------------------ | --------------------------------------------------------------------------------- |
| `Mailtrap API token cannot be empty`       | Set `MAILTRAP_TOKEN` in `.env`.                                                   |
| `Unknown Mailtrap endpoint "...".`         | `endpoint` must be one of: `transactional`, `bulk`, `sandbox`.                    |
| `Sandbox endpoint requires an inbox_id`    | Set `MAILTRAP_INBOX_ID` to a valid inbox ID.                                      |
| `inbox_id must be a positive integer`      | Pass the inbox ID as an integer, or use the `(int)` cast from `env()`.             |
| `cURL error 60: SSL certificate problem`   | Update CA certificates, or set `http.verify` to a CA bundle path.                 |
| `Connection timed out`                     | Increase `http.timeout` and `http.connect_timeout`.                               |
| `Mailtrap API returned HTTP 4xx/5xx`       | Verify your API token. Switch to the `log` mailer temporarily to inspect the raw payload. |

## Contributing

```bash
git clone https://github.com/ronald2wing/laravel-mailtrap.git
cd laravel-mailtrap
composer install
```

### Quality checks

| Command                | Purpose                                               |
| ---------------------- | ----------------------------------------------------- |
| `composer run test`    | PHPUnit with Clover + HTML coverage → `coverage/`     |
| `composer run lint`    | Check formatting with Pint (read-only)                |
| `composer run format`  | Apply formatting with Pint                            |
| `composer run analyse` | PHPStan level 8                                       |
| `composer run check`   | lint → analyse → test                                 |

## Security

- Never commit API tokens.
- Always enable SSL verification in production.
- Validate file types and sizes before attaching user-supplied content.

## License

MIT. See [LICENSE](LICENSE) for details.
