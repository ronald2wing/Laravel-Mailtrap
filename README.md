# Laravel Mailtrap Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![Total Downloads](https://img.shields.io/packagist/dt/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![PHP Version](https://img.shields.io/packagist/php-v/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![License](https://img.shields.io/packagist/l/ronald2wing/laravel-mailtrap.svg?style=flat-square)](LICENSE)
[![GitHub Actions](https://img.shields.io/github/actions/workflow/status/ronald2wing/laravel-mailtrap/php.yml?branch=main&style=flat-square)](https://github.com/ronald2wing/laravel-mailtrap/actions)
[![Codecov](https://img.shields.io/codecov/c/github/ronald2wing/laravel-mailtrap?style=flat-square)](https://codecov.io/gh/ronald2wing/laravel-mailtrap)

A Laravel mail driver for sending emails through the [Mailtrap.io](https://mailtrap.io) Email Sending Service API. Seamlessly integrate Laravel with Mailtrap's sending API while using Laravel's familiar mail API.

## ✨ Features

- **Easy Setup**: Simple configuration with Laravel's mail system
- **Full API Support**: Categories, attachments, CC/BCC, custom headers
- **Laravel Compatible**: Works with Laravel 10.x, 11.x, and 12.x
- **Clean Codebase**: Well-documented with comprehensive tests
- **Robust Error Handling**: Clear error messages and debugging
- **Email Analytics**: Mailtrap categories for tracking
- **UTF-8 Support**: Full international character support
- **Customizable**: Configurable API endpoints and HTTP client
- **High Performance**: Optimized payload construction and HTTP requests
- **Comprehensive Testing**: 27+ tests covering all features

## 📋 Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- GuzzleHTTP 7.0 or higher
- Required PHP extensions: curl, json, mbstring, openssl (recommended)

## 🚀 Quick Start

### 1. Install via Composer

```bash
composer require ronald2wing/laravel-mailtrap
```

The package uses Laravel's auto-discovery, so the service provider will be registered automatically.

### 2. Configure Environment Variables

Add the following to your `.env` file:

```env
MAIL_MAILER=mailtrap
MAILTRAP_TOKEN=your_mailtrap_api_token_here
```

### 3. Configure Services

Add the Mailtrap configuration to your `config/services.php` file:

```php
'mailtrap' => [
    'token' => env('MAILTRAP_TOKEN', ''),

    // Optional: Custom API endpoint (for testing or custom deployments)
    // 'endpoint' => 'https://custom.api.mailtrap.io/api/send',

    // Optional: Guzzle HTTP client configuration
    // 'guzzle' => [
    //     'timeout' => 30,
    //     'connect_timeout' => 10,
    //     'verify' => true,
    // ],
],
```

### 4. Configure Mail Settings

Update your `config/mail.php` file to use the Mailtrap transport:

```php
'default' => env('MAIL_MAILER', 'mailtrap'),

'mailers' => [
    'mailtrap' => [
        'transport' => 'mailtrap',
    ],

    // ... other mailers (smtp, log, etc.)
],
```

## 🔑 Obtaining Your Mailtrap API Token

1. Log in to your [Mailtrap account](https://mailtrap.io)
2. Navigate to your sending domain settings
3. Go to the "API" or "Integration" section
4. Copy your API token
5. Add it to your `.env` file as `MAILTRAP_TOKEN`

## 📝 Usage

### Basic Email Sending

```php
use Illuminate\Support\Facades\Mail;

// Send a basic email
Mail::to('recipient@example.com')
    ->send(new WelcomeEmail());

// Send with multiple recipients
Mail::to(['user1@example.com', 'user2@example.com'])
    ->cc('manager@example.com')
    ->bcc('admin@example.com')
    ->send(new AnnouncementEmail());
```

### Using Mailable Classes

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function build()
    {
        return $this->subject('Welcome to Our Service, ' . $this->user->name)
                    ->view('emails.welcome')
                    ->text('emails.welcome_plain')
                    ->with(['user' => $this->user]);
    }
}
```

### Mailtrap Categories for Analytics

Mailtrap categories help you track email performance and analytics:

```php
use Illuminate\Support\Facades\Mail;

// Using send method with category header
Mail::send('emails.welcome', $data, function ($message) {
    $message->to('user@example.com')
            ->subject('Welcome')
            ->header('X-Mailtrap-Category', 'welcome-emails');
});

// In a Mailable class
class WelcomeEmail extends Mailable
{
    public function build()
    {
        return $this->view('emails.welcome')
                    ->header('X-Mailtrap-Category', 'welcome-emails');
    }
}

// Multiple categories (comma-separated)
Mail::send('emails.newsletter', $data, function ($message) {
    $message->to('subscriber@example.com')
            ->subject('Weekly Newsletter')
            ->header('X-Mailtrap-Category', 'newsletter,marketing,weekly');
});
```

### Attachments

```php
use Illuminate\Support\Facades\Mail;

// Single attachment
Mail::send('emails.invoice', $data, function ($message) {
    $message->to('customer@example.com')
            ->subject('Your Invoice')
            ->attach('/path/to/invoice.pdf', [
                'as' => 'invoice-2024.pdf',
                'mime' => 'application/pdf',
            ]);
});

// Multiple attachments
Mail::send('emails.report', $data, function ($message) {
    $message->to('manager@example.com')
            ->subject('Monthly Report')
            ->attach('/path/to/report.pdf')
            ->attach('/path/to/data.xlsx')
            ->attach('/path/to/chart.png');
});

// Inline attachments (embedded images)
Mail::send('emails.newsletter', $data, function ($message) {
    $message->to('subscriber@example.com')
            ->subject('Newsletter with Images')
            ->attach('/path/to/logo.png', [
                'as' => 'logo.png',
                'mime' => 'image/png',
            ]);
});
```

### CC and BCC Recipients

```php
use Illuminate\Support\Facades\Mail;

// With CC and BCC
Mail::send('emails.announcement', $data, function ($message) {
    $message->to('primary@example.com')
            ->cc(['cc1@example.com', 'cc2@example.com'])
            ->bcc(['bcc1@example.com', 'bcc2@example.com'])
            ->subject('Important Announcement');
});

// In Mailable class
class TeamUpdateEmail extends Mailable
{
    public function build()
    {
        return $this->view('emails.team-update')
                    ->to('team@example.com')
                    ->cc('manager@example.com')
                    ->bcc('hr@example.com')
                    ->subject('Team Update');
    }
}
```

### Custom Headers and Reply-To

```php
use Illuminate\Support\Facades\Mail;

// Custom headers and reply-to
Mail::send('emails.contact', $data, function ($message) {
    $message->to('support@example.com')
            ->replyTo('user@example.com', 'John Doe')
            ->header('X-Priority', '1')
            ->header('X-Custom-ID', '12345')
            ->header('X-Tracking-ID', 'TRACK-789')
            ->subject('Support Request');
});

// Priority headers for urgent emails
Mail::send('emails.urgent', $data, function ($message) {
    $message->to('admin@example.com')
            ->header('X-Priority', '1') // High priority
            ->header('Importance', 'high')
            ->subject('URGENT: System Alert');
});
```

### HTML and Text Emails

```php
use Illuminate\Support\Facades\Mail;

// HTML email with fallback text version
Mail::send(['html' => 'emails.welcome', 'text' => 'emails.welcome_plain'], $data, function ($message) {
    $message->to('user@example.com')
            ->subject('Welcome Email');
});

// Mailable with both HTML and text views
class WelcomeEmail extends Mailable
{
    public function build()
    {
        return $this->subject('Welcome')
                    ->view('emails.welcome') // HTML view
                    ->text('emails.welcome_plain'); // Plain text view
    }
}
```

## ⚙️ Advanced Configuration

### Custom API Endpoint

For testing or custom deployments, you can specify a different API endpoint:

```php
// In config/services.php
'mailtrap' => [
    'token' => env('MAILTRAP_TOKEN', ''),
    'endpoint' => 'https://sandbox.api.mailtrap.io/api/send',
],
```

### HTTP Client Configuration

Customize the Guzzle HTTP client for specific needs:

```php
// In config/services.php
'mailtrap' => [
    'token' => env('MAILTRAP_TOKEN', ''),
    'guzzle' => [
        'timeout' => 30, // Request timeout in seconds
        'connect_timeout' => 10, // Connection timeout in seconds
        'verify' => false, // Disable SSL verification (not recommended for production)
        'proxy' => 'http://proxy.example.com:8080', // Use a proxy
        'headers' => [
            'User-Agent' => 'MyApp/1.0',
        ],
    ],
],
```

### Multiple Mailtrap Configurations

You can configure multiple Mailtrap accounts for different purposes:

```php
// In config/services.php
'mailtrap' => [
    'default' => [
        'token' => env('MAILTRAP_TOKEN_DEFAULT', ''),
    ],
    'marketing' => [
        'token' => env('MAILTRAP_TOKEN_MARKETING', ''),
        'endpoint' => 'https://send.api.mailtrap.io/api/send',
    ],
    'transactions' => [
        'token' => env('MAILTRAP_TOKEN_TRANSACTIONS', ''),
        'guzzle' => [
            'timeout' => 60, // Longer timeout for transactional emails
        ],
    ],
],
```

Then use them in your mail configuration:

```php
// In config/mail.php
'mailers' => [
    'mailtrap_default' => [
        'transport' => 'mailtrap',
        'config' => 'mailtrap.default',
    ],
    'mailtrap_marketing' => [
        'transport' => 'mailtrap',
        'config' => 'mailtrap.marketing',
    ],
    'mailtrap_transactions' => [
        'transport' => 'mailtrap',
        'config' => 'mailtrap.transactions',
    ],
],
```

## 🧪 Testing

### Testing in Your Application

When testing your application that uses this package:

```php
// In your test
Mail::fake();

// Perform actions that send emails
$user->sendWelcomeEmail();

// Assert emails were sent
Mail::assertSent(WelcomeEmail::class, function ($mail) use ($user) {
    return $mail->hasTo($user->email) &&
           $mail->hasHeader('X-Mailtrap-Category', 'welcome-emails');
});
```

## 🔧 Troubleshooting

### Common Issues

#### 1. Missing API Token Error

```
InvalidArgumentException: Mailtrap API token is missing. Please configure it in config/services.php under the "mailtrap.token" key or set MAILTRAP_TOKEN in your .env file.
```

**Solution**: Ensure your `.env` file contains `MAILTRAP_TOKEN=your_token_here` and the token is correctly set in `config/services.php`.

#### 2. SSL Certificate Verification Failed

```
GuzzleHttp\Exception\RequestException: cURL error 60: SSL certificate problem: unable to get local issuer certificate
```

**Solution**:

- Update your CA certificates: `sudo update-ca-certificates`
- Or temporarily disable SSL verification (not recommended for production):

  ```php
  'guzzle' => [
      'verify' => false,
  ],
  ```

#### 3. Timeout Errors

```
GuzzleHttp\Exception\ConnectException: Connection timed out after 10000 milliseconds
```

**Solution**: Increase timeout settings:

```php
'guzzle' => [
    'timeout' => 60,
    'connect_timeout' => 30,
],
```

#### 4. UTF-8 Character Issues

If you're experiencing issues with international characters:

**Solution**: Ensure your email content is UTF-8 encoded and your database/application uses UTF-8 charset.

### Debugging

Enable detailed logging to debug email sending:

```php
// In config/mail.php
'mailers' => [
    'mailtrap' => [
        'transport' => 'mailtrap',
    ],
    'log' => [
        'transport' => 'log',
        'channel' => env('MAIL_LOG_CHANNEL'),
    ],
],
```

Then switch to log driver temporarily:

```env
MAIL_MAILER=log
```

## 🤝 Contributing

We welcome contributions! Please follow these steps:

### 1. Fork and Clone

```bash
git clone https://github.com/ronald2wing/laravel-mailtrap.git
cd laravel-mailtrap
composer install
```

### 2. Create a Feature Branch

```bash
git checkout -b feature/amazing-feature
```

### 3. Make Your Changes

Follow the existing code style and patterns:

- Use type hints for all method parameters and return types
- Add PHPDoc blocks for public/protected methods
- Follow PSR-12 coding standards
- Write tests for new functionality

### 4. Run Quality Checks

```bash
composer run check
```

### 5. Commit and Push

```bash
git commit -m 'Add amazing feature'
git push origin feature/amazing-feature
```

### 6. Open a Pull Request

Create a pull request with a clear description of your changes.

## 📊 Performance Tips

1. **Batch Processing**: When sending multiple emails, consider using Laravel's queue system
2. **Connection Reuse**: The HTTP client reuses connections by default
3. **Attachment Size**: Compress large attachments before sending
4. **Caching**: Cache email templates when possible
5. **Queue Workers**: Use queue workers for better throughput

## 🔒 Security Considerations

1. **API Token Security**: Never commit API tokens to version control
2. **SSL Verification**: Always enable SSL verification in production
3. **Input Validation**: Validate all user inputs before including in emails
4. **Attachment Validation**: Validate file types and sizes for attachments
5. **Rate Limiting**: Implement rate limiting for email sending

## 📈 Monitoring and Analytics

### Mailtrap Dashboard

Monitor your email performance in the Mailtrap dashboard:

- Delivery rates
- Open rates
- Click-through rates
- Bounce rates
- Spam complaints

### Application Logging

```php
// Log email sending events
Mail::send('emails.welcome', $data, function ($message) {
    $message->to('user@example.com')
            ->subject('Welcome');

    Log::info('Welcome email sent to user@example.com');
});
```

## 🔗 Useful Links

- [Packagist](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
- [GitHub Repository](https://github.com/ronald2wing/laravel-mailtrap)
- [Issue Tracker](https://github.com/ronald2wing/laravel-mailtrap/issues)
- [Mailtrap Documentation](https://
