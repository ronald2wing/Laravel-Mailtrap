# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-11-11

### Added
- **CI/CD Pipeline**: GitHub Actions workflow for automated testing and code quality checks
- **Static Analysis**: PHPStan configuration with level 8 analysis
- **Build Configuration**: Added build directory to gitignore

### Changed
- **Workflow Optimization**: Updated GitHub Actions workflow with improved caching and matrix testing

## [1.0.0] - 2025-11-11

### Added
- **Initial Release**: Complete Laravel Mailtrap Driver package
- **Core Integration**: Full Mailtrap Email Sending API integration
- **Framework Support**: Laravel 10.x, 11.x, and 12.x compatibility
- **Email Features**:
  - Text and HTML email support
  - Multipart emails (both text and HTML)
  - Attachments (regular and inline)
  - CC and BCC recipients
  - Custom headers
  - Mailtrap categories
  - Reply-To functionality
  - International character support (UTF-8)
- **Configuration**:
  - Customizable API endpoints
  - HTTP client configuration options
  - Environment-based configuration
- **Developer Experience**:
  - Comprehensive test suite with 18 tests covering all features
  - Code quality tools integration (PHPStan and Laravel Pint)
  - Static analysis configuration with PHPStan level 8
  - Code formatting with Laravel Pint standards
  - Comprehensive PHPDoc type annotations throughout codebase
  - Auto-discovery for Laravel service provider

### Technical Details
- **Dependencies**: PHP 8.2+, GuzzleHTTP 7.0+
- **Architecture**: Symfony Mailer transport implementation
- **Testing**: Orchestra Testbench with Mockery for HTTP mocking
- **Code Quality**: 100% type coverage with PHPStan level 8

### Breaking Changes
- None (initial release)

### Migration Guide
- No migration needed for initial release
