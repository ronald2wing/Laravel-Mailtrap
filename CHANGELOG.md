# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-04-27

### Added

- Mailtrap Email Sending API transport via Symfony Mailer.
- Text and HTML email support.
- Multipart emails (both text and HTML).
- File attachments (regular and inline).
- CC and BCC recipients.
- Custom headers and Reply-To (multiple addresses joined per RFC 5322).
- Mailtrap categories via `X-Mailtrap-Category` header.
- Template support via `X-Mailtrap-Template-Uuid`, `X-Mailtrap-Template-Variables`, and `X-Mailtrap-Custom-Variables` headers.
- Sandbox, Bulk, and Transactional endpoints with `inbox_id` configuration.
- `MailtrapConfig::$baseUrl` — optional URL override for proxies/testing.
- HTTP client options via `http` config key (defaults: `connect_timeout: 10`, `timeout: 30`).
- `MailtrapApiException::fromResponse()` static factory with `$statusCode` and `$body` properties.
- International character support (UTF-8).
- PHP 8.3+, Laravel 10.x–13.x.
- PHPStan level 8 with zero ignored errors.
- Comprehensive test suite.
- CI via GitHub Actions.

[1.0.0]: https://github.com/ronald2wing/laravel-mailtrap/releases/tag/1.0.0
