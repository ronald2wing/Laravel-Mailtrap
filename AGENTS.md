# Laravel Mailtrap Driver — Agent Guide

## Commands

```bash
composer run check         # lint → analyse → test (preferred local workflow)
composer run test          # PHPUnit with Clover + HTML coverage → coverage/
composer run analyse       # PHPStan level 8, --memory-limit=1G
composer run format        # Pint — modifies files in-place
composer run lint          # Pint --test — checks formatting without modifying
```

**Coverage gotcha:** `composer run test` and `composer run check` both require a coverage driver (Xdebug or pcov). Without one, PHPUnit exits with error. For local runs:
```bash
vendor/bin/phpunit --no-coverage
```

Run a single test:
```bash
vendor/bin/phpunit --filter MethodName --no-coverage
```

## CI

CI (`.github/workflows/php.yml`) runs in this order: `composer validate --strict` → `test` → `lint` → `analyse`. Different from `composer run check` (lint → analyse → test). CI matrix covers PHP 8.3/8.4/8.5 × Laravel 10.x-13.x, excluding Laravel 10 from newer PHP versions.

## Architecture

Five source files in `src/`, all under namespace `Ronald2Wing\LaravelMailtrap\` (PSR-4):

| File | Role |
|------|------|
| `MailtrapServiceProvider.php` | Registers the `mailtrap` transport via `MailManager::extend()`. Auto-discovered via `extra.laravel.providers` in `composer.json`. Reads from `config('mailtrap')` by default; each mailer may override via `'config' => 'mailtrap.marketing'` (dot-notation sub-key). Publishes `config/mailtrap.php` with tag `mailtrap-config`. |
| `MailtrapTransport.php` | Symfony `AbstractTransport`. Uses Laravel HTTP client (`Illuminate\Http\Client\Factory`) to POST JSON from `MessagePayloadFactory` to `apiUrl()`. `__toString()` includes the host (e.g. `mailtrap+api://send.api.mailtrap.io`). `firstMessageId()` extracts the message ID from the response. |
| `MessagePayloadFactory.php` | Translates Symfony `Email` + `Envelope` into Mailtrap's JSON payload. Pure, no I/O. Entry point: `build()`. `DIRECTIVES` constant maps Mailtrap headers to payload keys. |
| `MailtrapConfig.php` | `final readonly` value object — token, endpoint enum, optional inbox id, optional base URL override, HTTP options. Factory: `fromArray()`. Missing optional keys use constructor defaults (e.g. endpoint → Transactional, HTTP → connect_timeout:10/timeout:30). |
| `MailtrapEndpoint.php` | String-backed enum: `Transactional`, `Bulk`, `Sandbox`. Has `baseUrl()` and `requiresInboxId()` methods. |

Exceptions live in `src/Exceptions/`:
- `MailtrapException` (base, extends `RuntimeException`)
- `InvalidConfigurationException extends MailtrapException` — token empty, bad URL, missing/invalid inbox_id, non-integer inbox_id, non-array HTTP config
- `MailtrapApiException extends MailtrapException` — non-2xx HTTP responses. Has `$statusCode` (int) and `$body` (string) readonly properties. Named constructor: `fromResponse(Response $response)`.
- `MailtrapTransportException extends MailtrapException` — connection failures (DNS, timeout, TLS). Named constructor: `fromConnectionError(ConnectionException $e)`. Constructor is private.

## Static Analysis

PHPStan runs at **level 8** with **no ignored errors** on both `src/` and `tests/`. Never add baseline entries or `@phpstan-ignore` comments.

## Testing

- **Base classes**: `Orchestra\Testbench\TestCase` for container-aware tests; `PHPUnit\Framework\TestCase` for pure unit tests.
- **HTTP mocking**: `Http::fake()` + `Http::assertSent(fn (Request $r) => ...)` instead of Guzzle `MockHandler`/`HandlerStack`.
- `firstMessageId` (private) is tested indirectly through `send()` results (checking debug output).
- `getEnvironmentSetUp()` configures `mailtrap.token`, `mail.default`, `mail.mailers.mailtrap`, and `mail.from` for integration tests.
- Test trait `BuildsTestEmails` provides shared constants (`SENDER_EMAIL`, `RECIPIENT_EMAIL`, `SENDER_NAME`, `TEST_TOKEN`, `API_URL`) and `basicEmail()`.

## Conventions

- `declare(strict_types=1)` in every file.
- `composer.json` has `sort-packages: true` — Composer auto-sorts after `composer require`. Keep it valid for CI.
- PHPDoc with `@param` array shapes on every public/protected method.

## Extension Guide: Adding a Mailtrap-specific header

1. Add an entry to `MessagePayloadFactory::DIRECTIVES`: specify the `key` (payload field name) and whether the value needs `json_decode` (`'json' => true`).
2. Done. `extractDirectives()` picks it up automatically, and `reservedHeaderNames()` strips it from the forwarded headers block.
