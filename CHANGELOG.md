# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-11

### Added

- Initial release of Laravel Mailtrap Driver package
- Complete Mailtrap Email Sending API integration
- Support for Laravel 10.x, 11.x, and 12.x
- Comprehensive email features:
  - Text and HTML email support
  - Multipart emails (both text and HTML)
  - File attachments (regular and inline)
  - CC and BCC recipients
  - Custom headers and Reply-To functionality
  - Mailtrap categories for analytics
  - International character support (UTF-8)
- Advanced configuration options:
  - Customizable API endpoints
  - HTTP client configuration
  - Environment-based configuration
- Developer experience features:
  - Comprehensive test suite (27 tests)
  - Code quality tools integration (PHPStan Level 8, Laravel Pint)
  - Static analysis with 100% type coverage
  - Auto-discovery for Laravel service provider
  - CI/CD pipeline with GitHub Actions
  - Complete documentation (README.md, AGENTS.md)

### Technical Details

- **Dependencies**: PHP 8.2+, GuzzleHTTP 7.0+
- **Architecture**: Symfony Mailer transport implementation
- **Testing**: Orchestra Testbench with Mockery for HTTP mocking
- **Code Quality**: PHPStan Level 8 with full type coverage

### Breaking Changes

- None (initial release)

## Versioning Policy

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html):

- **MAJOR** version for incompatible API changes
- **MINOR** version for new functionality in a backward compatible manner
- **PATCH** version for backward compatible bug fixes

## Release Process

1. Update version in `composer.json`
2. Update this CHANGELOG.md file
3. Create git tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`
4. Push tag: `git push origin vX.Y.Z`
5. GitHub Actions will run tests and create a release automatically

## Contributing to the Changelog

When adding entries to the changelog, please follow these guidelines:

1. **Group changes by type**: Added, Changed, Deprecated, Removed, Fixed, Security
2. **Use present tense**: "Add feature" not "Added feature"
3. **Reference issues and PRs**: Use GitHub issue/PR numbers when applicable
4. **Be descriptive but concise**: Explain what changed and why it matters
5. **Include migration notes**: For breaking changes, provide clear migration instructions

## Links

- [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
- [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
- [GitHub Releases](https://github.com/ronald2wing/laravel-mailtrap/releases)
