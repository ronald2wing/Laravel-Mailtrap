# Laravel Mailtrap Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![Total Downloads](https://img.shields.io/packagist/dt/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![PHP Version](https://img.shields.io/packagist/php-v/ronald2wing/laravel-mailtrap.svg?style=flat-square)](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
[![License](https://img.shields.io/packagist/l/ronald2wing/laravel-mailtrap.svg?style=flat-square)](LICENSE)

A Laravel mail driver for sending emails through the [Mailtrap.io](https://mailtrap.io) Email Sending Service API.

This package provides seamless integration between Laravel and Mailtrap's sending API, allowing you to send emails through Mailtrap while using Laravel's familiar mail API.

## ✨ Features

- **🚀 Easy Integration**: Simple setup with Laravel's mail configuration
- **📧 Full Feature Support**: Supports all Mailtrap API features including categories, attachments, CC/BCC
- **🔄 Laravel Compatibility**: Works with Laravel 10.x, 11.x, and 12.x
- **💻 Modern Codebase**: Clean, well-documented code with comprehensive tests
- **🛡️ Error Handling**: Robust error handling and debugging capabilities
- **📊 Analytics**: Support for Mailtrap categories for email tracking
- **🌍 International**: Full UTF-8 support for international characters
- **🔧 Customizable**: Configurable API endpoints and HTTP client options

## 📋 Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- GuzzleHTTP 7.0 or higher

## 🚀 Installation

Install the package via Composer:

```bash
composer require ronald2wing/laravel-mailtrap
```

The package will automatically register itself using Laravel's package auto-discovery.

## ⚙️ Configuration

### Environment Configuration

Add your Mailtrap API configuration to your `.env` file:

```env
MAIL_MAILER=mailtrap
MAILTRAP_TOKEN=your_mailtrap_api_token_here
```

### Services Configuration

Add your Mailtrap API configuration to `config/services.php`:

```php
'mailtrap' => [
    'token' => env('MAILTRAP_TOKEN', ''), // Your Mailtrap API token
],
```

### Mail Configuration

Configure Laravel to use the Mailtrap driver in `config/mail.php`:

```php
'default' => env('MAIL_MAILER', 'mailtrap'),

'mailers' => [
    'mailtrap' => [
        'transport' => 'mailtrap',
    ],
    // ... other mailers
],
```

### Advanced Configuration

For advanced use cases, you can customize the HTTP client configuration:

```php
'mailtrap' => [
    'token' => env('MAILTRAP_TOKEN', ''),
    'guzzle' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'headers' => [
            'User-Agent' => 'Your-App-Name/1.0',
        ],
    ],
],
```

## 📝 Usage

### Basic Email Sending

Send emails using Laravel's standard mail API:

```php
use Illuminate\Support\Facades\Mail;

Mail::to('recipient@example.com')
    ->send(new WelcomeEmail());
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

    public function build()
    {
        return $this->subject('Welcome to Our Service')
                    ->view('emails.welcome')
                    ->text('emails.welcome_plain');
    }
}
```

### Using Mailtrap Categories

You can add Mailtrap categories to track email analytics:

```php
use Illuminate\Support\Facades\Mail;

// Using the send method
Mail::send('emails.welcome', $data, function ($message) {
    $message->to('user@example.com')
            ->subject('Welcome to Our Service')
            ->header('X-Mailtrap-Category', 'welcome-emails');
});

// Using a Mailable class
class WelcomeEmail extends Mailable
{
    public function build()
    {
        return $this->view('emails.welcome')
                    ->header('X-Mailtrap-Category', 'welcome-emails');
    }
}
```

### Sending with Attachments

```php
Mail::send('emails.invoice', $data, function ($message) {
    $message->to('customer@example.com')
            ->subject('Your Invoice')
            ->attach('/path/to/invoice.pdf');
});

// Multiple attachments
Mail::send('emails.newsletter', $data, function ($message) {
    $message->to('subscriber@example.com')
            ->subject('Monthly Newsletter')
            ->attach('/path/to/newsletter.pdf')
            ->attach('/path/to/special-offer.jpg');
});
```

### Sending with CC and BCC

```php
Mail::send('emails.announcement', $data, function ($message) {
    $message->to('primary@example.com')
            ->cc(['cc1@example.com', 'cc2@example.com'])
            ->bcc('bcc@example.com')
            ->subject('Important Announcement');
});
```

### Custom Headers and Reply-To

```php
Mail::send('emails.contact', $data, function ($message) {
    $message->to('support@example.com')
            ->replyTo('user@example.com', 'John Doe')
            ->header('X-Priority', '1')
            ->header('X-Custom-ID', '12345')
            ->subject('Support Request');
});
```

## 🔑 API Token

To get your Mailtrap API token:

1. Log in to your [Mailtrap account](https://mailtrap.io)
2. Navigate to your sending domain settings
3. Copy the API token from the integration section
4. Add it to your `.env` file as `MAILTRAP_TOKEN`

## 🧪 Testing

The package includes comprehensive tests covering all features. Run them with:

```bash
./vendor/bin/phpunit
```

### Test Features Covered

- ✅ Email sending (text, HTML, multipart)
- ✅ Recipients (to, cc, bcc)
- ✅ Attachments (regular, inline)
- ✅ Headers and metadata
- ✅ Error handling
- ✅ International character support
- ✅ Custom API endpoints

## 🎯 Code Quality

This package maintains high code quality standards with the following tools:

### Static Analysis
Run PHPStan for static analysis:

```bash
composer run analyse
```

### Code Formatting
Format code with Laravel Pint:

```bash
composer run lint
```

### Full Quality Check
Run both tests and code quality checks:

```bash
./vendor/bin/phpunit && composer run analyse && composer run lint
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

```bash
git clone https://github.com/ronald2wing/laravel-mailtrap.git
cd laravel-mailtrap
composer install
```

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 🆘 Support

If you encounter any issues or have questions:

1. Check the [documentation](#) for common solutions
2. Search [existing issues](https://github.com/ronald2wing/laravel-mailtrap/issues)
3. Open a [new issue](https://github.com/ronald2wing/laravel-mailtrap/issues/new) with detailed information

## 🔗 Links

- [Packagist](https://packagist.org/packages/ronald2wing/laravel-mailtrap)
- [GitHub Repository](https://github.com/ronald2wing/laravel-mailtrap)
- [Mailtrap Documentation](https://help.mailtrap.io/)
- [Laravel Documentation](https://laravel.com/docs/mail)

---

**Built with ❤️ for the Laravel community**
