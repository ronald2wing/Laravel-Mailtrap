# Laravel Mailtrap Driver

## Project Overview

**Project Name**: Laravel Mailtrap Driver
**Package Name**: `ronald2wing/laravel-mailtrap`
**Repository**: <https://github.com/ronald2wing/laravel-mailtrap>
**Maintainer**: Ronald Wong (<ronald2wing@gmail.com>)
**License**: MIT
**Current Version**: 1.1.0 (as of 2025-11-11)

### Purpose

A Laravel mail driver for sending emails through the Mailtrap.io Email Sending Service API. Provides seamless integration between Laravel and Mailtrap's sending API while using Laravel's familiar mail API.

## Technical Stack

### Core Dependencies

- **PHP**: 8.2+
- **Laravel**: 10.x, 11.x, 12.x
- **GuzzleHTTP**: 7.0+
- **Symfony Mailer**: Via Laravel's mail system

### Development Dependencies

- **PHPUnit**: For testing
- **PHPStan**: Level 8 static analysis
- **Laravel Pint**: Code formatting
- **Orchestra Testbench**: Laravel package testing
- **Mockery**: Mocking for tests

## Project Structure

```
laravel-mailtrap-driver/
├── src/
│   ├── MailtrapServiceProvider.php    # Laravel service provider
│   └── MailtrapTransport.php          # Core transport implementation
├── tests/
│   └── MailtrapTransportTest.php      # Comprehensive test suite (19 tests)
├── .github/workflows/
│   └── php.yml                        # CI/CD pipeline
├── composer.json                      # Package configuration
├── README.md                          # Documentation
├── CHANGELOG.md                       # Version history
├── phpstan.neon.dist                  # PHPStan configuration
├── phpunit.xml.dist                   # PHPUnit configuration
├── .editorconfig                      # Code style configuration
└── .gitattributes                     # Git export configuration
```

## Core Components

### 1. MailtrapServiceProvider

**Location**: `src/MailtrapServiceProvider.php`
**Purpose**: Registers the Mailtrap transport with Laravel's mail system.

**Key Responsibilities**:

- Extends Laravel's mail manager with 'mailtrap' transport
- Validates configuration (API token presence and type)
- Creates configured HTTP client with Guzzle options
- Retrieves configuration from `services.mailtrap` config key

**Configuration Key**: `services.mailtrap`
**Configuration Reference**: See the [Configuration](#configuration) section below for setup details.

### 2. MailtrapTransport

**Location**: `src/MailtrapTransport.php`
**Purpose**: Implements Symfony Mailer transport for Mailtrap API integration.

**Key Features**:

- REST API integration with Mailtrap's email sending service
- Support for text, HTML, and multipart emails
- Attachment handling (regular and inline)
- Recipient management (To, CC, BCC)
- Custom headers and Mailtrap categories
- UTF-8 international character support

**API Endpoint**: `https://send.api.mailtrap.io/api/send` (configurable)

**Excluded Headers**: The following headers are excluded from custom header forwarding:

- X-Mailtrap-Category (handled separately)
- Reply-To (handled separately)
- Subject, From, To, Cc, Bcc
- Date, Message-ID, MIME-Version, Content-Type

## Configuration

### Environment Setup

1. Add to `.env`:

    ```env
    MAIL_MAILER=mailtrap
    MAILTRAP_TOKEN=your_mailtrap_api_token_here
    ```

2. Add to `config/services.php`:

    ```php
    'mailtrap' => [
        'token' => env('MAILTRAP_TOKEN', ''),
    ],
    ```

3. Configure in `config/mail.php`:

    ```php
    'default' => env('MAIL_MAILER', 'mailtrap'),

    'mailers' => [
        'mailtrap' => [
            'transport' => 'mailtrap',
        ],
    ],
    ```

### Advanced Configuration

For advanced use cases, you can customize the HTTP client:

```php
'mailtrap' => [
    'token' => env('MAILTRAP_TOKEN', ''),
    'guzzle' => [ // Optional
        'timeout' => 30,
        'connect_timeout' => 10,
        'headers' => ['User-Agent' => 'Your-App-Name/1.0'],
    ],
]
```

## Usage Examples

### Basic Email Sending

```php
use Illuminate\Support\Facades\Mail;

Mail::to('recipient@example.com')
    ->send(new WelcomeEmail());
```

### Using Mailtrap Categories

```php
Mail::send('emails.welcome', $data, function ($message) {
    $message->to('user@example.com')
            ->subject('Welcome')
            ->header('X-Mailtrap-Category', 'welcome-emails');
});
```

### With Attachments

```php
Mail::send('emails.invoice', $data, function ($message) {
    $message->to('customer@example.com')
            ->subject('Your Invoice')
            ->attach('/path/to/invoice.pdf');
});
```

### With CC and BCC

```php
Mail::send('emails.announcement', $data, function ($message) {
    $message->to('primary@example.com')
            ->cc(['cc1@example.com', 'cc2@example.com'])
            ->bcc('bcc@example.com')
            ->subject('Important Announcement');
});
```

## Testing

### Test Suite

**Location**: `tests/MailtrapTransportTest.php`
**Coverage**: 19 comprehensive tests covering:

- Transport creation with advanced Guzzle configuration
- Transport creation with minimal configuration
- Email sending (text, HTML, multipart)
- Recipients (to, cc, bcc, multiple recipient types)
- Attachments (regular, inline, mixed, multiple)
- Headers and metadata (categories, custom headers, reply-to)
- Error handling (API request failures)
- International character support (UTF-8)
- Custom API endpoints
- Transport string representation

### Running Tests

```bash
# Run all tests
composer run test

# Run tests with coverage
composer run test-coverage

# Run specific test
./vendor/bin/phpunit --filter test_sends_plain_text_email_with_category
```

### Test Configuration

- Uses Orchestra Testbench for Laravel package testing
- Mockery for HTTP client mocking
- Configurable test environment in `getEnvironmentSetUp()`

## Code Quality & Static Analysis

### PHPStan (Level 8)

```bash
composer run analyse
```

- Configured in `phpstan.neon.dist`
- Level 8 (strict) analysis
- Ignores common Laravel/Symfony patterns
- Memory limit: 1GB

### Laravel Pint

```bash
composer run lint
```

- Code formatting according to Laravel standards
- Auto-fixes code style issues

### Full Quality Check

```bash
./vendor/bin/phpunit && composer run analyse && composer run lint
```

## CI/CD Pipeline

### GitHub Actions Workflow

**Location**: `.github/workflows/php.yml`
**Triggers**: Push/Pull Request to master branch
**Matrix Testing**: PHP 8.2, 8.3, 8.4

**Workflow Steps**:

1. Setup PHP with specified version
2. Validate composer.json
3. Cache Composer packages
4. Install dependencies
5. Run PHPUnit tests
6. Run PHPStan analysis
7. Run Laravel Pint

## Development Workflow

### Local Development Setup

```bash
git clone https://github.com/ronald2wing/laravel-mailtrap.git
cd laravel-mailtrap
composer install
```

### Making Changes

1. Create feature branch: `git checkout -b feature/amazing-feature`
2. Make changes and write tests
3. Run quality checks: `./vendor/bin/phpunit && composer run analyse && composer run lint`
4. Commit changes: `git commit -m 'Add some amazing feature'`
5. Push to branch: `git push origin feature/amazing-feature`
6. Open Pull Request

### Release Process

1. Update version in `composer.json`
2. Update `CHANGELOG.md` following Keep a Changelog format
3. Create git tag: `git tag -a v1.x.x -m "Release v1.x.x"`
4. Push tag: `git push origin v1.x.x`
5. GitHub Actions will run tests automatically

## Error Handling & Debugging

### Common Issues

1. **Missing API Token**

    ```
    InvalidArgumentException: Mailtrap API token is missing.
    ```

    **Solution**: Configure `MAILTRAP_TOKEN` in `.env` or `config/services.php`

2. **API Request Failures**
    - Check network connectivity
    - Verify API token validity
    - Check Mailtrap account status

### Debug Information

The transport appends debug information including:

- Message IDs from Mailtrap API response
- Request/response details (when debugging enabled)

## API Integration Details

### Mailtrap API Payload Structure

The transport builds payloads with the following structure:

```json
{
  "from": {"email": "...", "name": "..."},
  "to": [{"email": "...", "name": "..."}],
  "subject": "...",
  "text": "...",
  "html": "...",
  "cc": [...],
  "bcc": [...],
  "attachments": [...],
  "category": "...",
  "headers": {...}
}
```

### HTTP Client Configuration

Customizable via `services.mailtrap.guzzle` configuration:

- Timeout settings (default not set, must be configured)
- Connection timeout (default: 60 seconds)
- Custom headers
- Proxy configuration
- SSL options

## Performance Considerations

1. **HTTP Client Reuse**: The same HTTP client instance is reused
2. **Connection Pooling**: Guzzle handles connection pooling
3. **Timeout Configuration**: Default 60-second connection timeout, configurable
4. **Memory Usage**: Base64 encoding of attachments may increase memory usage

## Security Considerations

1. **API Token Security**: Store tokens in `.env`, never in code
2. **HTTPS**: All API calls use HTTPS
3. **Input Validation**: Email addresses and headers are validated
4. **Attachment Security**: Base64 encoding for binary safety

## Compatibility Matrix

| Laravel Version | PHP Version | Package Version | Status       |
| --------------- | ----------- | --------------- | ------------ |
| 12.x            | 8.2+        | 1.1.0           | ✅ Supported |
| 11.x            | 8.2+        | 1.1.0           | ✅ Supported |
| 10.x            | 8.2+        | 1.1.0           | ✅ Supported |

## Known Limitations & Future Enhancements

### Current Limitations

1. **Rate Limiting**: Subject to Mailtrap API rate limits
2. **Attachment Size**: Limited by Mailtrap API constraints
3. **Synchronous Only**: Currently only supports synchronous sending
4. **Batch Sending**: No native batch email support

### Potential Enhancements

1. **Async Support**: Add queue support for background sending
2. **Webhook Integration**: Add webhook handling for delivery status
3. **Template Support**: Integrate with Mailtrap email templates
4. **Retry Logic**: Implement retry mechanism for failed sends
5. **Custom Headers**: More flexible custom header handling

## Maintenance

### Regular Maintenance Tasks

- Update dependencies (composer update)
- Run test suite after dependency updates
- Check PHPStan for new issues
- Update compatibility matrix for new Laravel/PHP versions

### Before Release

- Run full test suite
- Run PHPStan analysis
- Run Laravel Pint
- Update CHANGELOG.md
- Update version in composer.json
- Verify all tests pass on all PHP versions

## Support & Resources

### Documentation

- [README.md](./README.md) - User documentation
- [CHANGELOG.md](./CHANGELOG.md) - Version history
- This handover document

### External Resources

- [Mailtrap Documentation](https://help.mailtrap.io/)
- [Laravel Mail Documentation](https://laravel.com/docs/mail)
- [GitHub Issues](https://github.com/ronald2wing/laravel-mailtrap/issues)
- [Packagist Page](https://packagist.org/packages/ronald2wing/laravel-mailtrap)

### Contact

- **Maintainer**: Ronald Wong (<ronald2wing@gmail.com>)
- **GitHub**: @ronald2wing
- **Issues**: <https://github.com/ronald2wing/laravel-mailtrap/issues>
