# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony bundle that provides health check functionality for monitoring application dependencies (database, Redis, S3, HTTP endpoints). It follows a security-first design with no sensitive information exposed in responses.

## Development Commands

### Docker Environment (Recommended)

The project uses Docker Compose with all necessary services pre-configured.

```bash
# Start services (MySQL, PostgreSQL, Redis, MinIO)
make up

# Install dependencies
make install

# Run tests
make test

# Run PHPStan (level 8)
make phpstan

# Check code style (PSR-12 + Yoda comparisons)
make cs-check

# Fix code style
make cs-fix

# Run all quality checks (PHPStan + CS + Tests)
make check

# Open shell in PHP container
make shell

# Stop services
make down
```

### Without Make

```bash
# Start services
docker compose up -d

# Run tests
docker compose exec php composer test

# Run specific test
docker compose exec php ./vendor/bin/phpunit tests/Path/To/Test.php

# Run PHPStan
docker compose exec php composer phpstan

# Fix code style
docker compose exec php composer cs-fix

# Run GrumPHP (all quality checks)
docker compose exec php composer qa
```

### Native PHP

```bash
composer test
composer phpstan
composer cs-fix
composer qa
```

## Architecture

### Core Components

**Value Objects**
- `HealthCheckStatus`: Enum (healthy/degraded/unhealthy) with helper methods
- `HealthCheckResult`: Readonly class containing check results (immutable)

**Service Layer**
- `HealthCheckInterface`: Contract with `#[AutoconfigureTag('health_check.checker')]` for automatic service discovery
- `AbstractHealthCheck`: Template Method pattern - provides timeout management, duration measurement, exception handling
- Concrete checks: `DatabaseHealthCheck`, `RedisHealthCheck`, `S3HealthCheck`, `HttpHealthCheck`

**Orchestration**
- `HealthCheckService`: Aggregates all checks via tagged iterator, determines overall status
- `HealthCheckController`: `/health` endpoint - returns 200 for healthy, 503 for unhealthy
- `PingController`: `/ping` endpoint - lightweight liveness probe (always returns 200)

**DI Container**
- `HealthCheckPass`: Compiler pass that collects tagged services
- `HealthCheckExtension`: Loads services and configuration

### Automatic Service Discovery

All classes implementing `HealthCheckInterface` are automatically tagged with `health_check.checker` via the `#[AutoconfigureTag]` attribute on the interface. No manual tagging required in services.yaml.

### Template Method Pattern

`AbstractHealthCheck` implements the public `check()` method and calls the protected abstract `doCheck()` method. Subclasses only need to implement `doCheck()` - timeout handling, timing, and exception handling are automatic.

### Critical vs Non-Critical Checks

Checks can be marked critical via `isCritical()`. If ANY critical check fails, the overall status is "unhealthy" (HTTP 503). Non-critical check failures are reported but don't affect overall status.

## Code Quality Standards

### PHPStan Level 8

- **Strictest static analysis** - all code must pass
- `treatPhpDocTypesAsCertain: false` in phpstan.neon for third-party library compatibility
- Optional dependencies (doctrine/dbal, league/flysystem, predis/predis) are in require-dev to satisfy PHPStan

### PHP-CS-Fixer

- **PSR-12** compliance
- **Yoda comparisons** required: `true === $var` not `$var === true`
- Strict types declaration: `declare(strict_types=1);` in ALL files
- Type hints for all parameters and return types

### GrumPHP

Pre-commit hooks run automatically:
- PHPUnit tests
- PHPStan analysis
- PHP-CS-Fixer

To bypass hooks (use sparingly): `git commit --no-verify`

## Security Design

### What is NEVER Exposed

- Database credentials, hostnames, versions
- Redis/service versions or stats
- Internal file paths
- Stack traces
- Exception details
- Response sizes, connection counts

### Generic Error Messages

All checks return generic messages like "Database operational" or "Redis connection failed" with empty metadata arrays. This is intentional - do NOT add detailed error information.

## Testing

### Running Tests

```bash
# All tests
composer test

# Single test file
./vendor/bin/phpunit tests/Controller/HealthCheckControllerTest.php

# With coverage (requires pcov)
./vendor/bin/phpunit --coverage-text
```

### Test Structure

- Unit tests: Test individual components in isolation
- Integration tests: Test with real services via Docker (database, Redis, etc.)
- Mocking: Use PHPUnit mocks for external dependencies when appropriate

## Creating New Health Checks

1. **Extend AbstractHealthCheck** (recommended):

```php
namespace App\HealthCheck;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;

class CustomCheck extends AbstractHealthCheck
{
    public function getName(): string
    {
        return 'custom';  // lowercase with underscores
    }

    public function getTimeout(): int
    {
        return 5;  // seconds
    }

    public function isCritical(): bool
    {
        return false;  // true if failure should return 503
    }

    protected function doCheck(): HealthCheckResult
    {
        // Your logic here
        // AbstractHealthCheck handles timing, timeout, exceptions

        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::HEALTHY,
            message: 'Custom check passed',
            duration: 0.0,  // Will be set by AbstractHealthCheck
            metadata: []     // ALWAYS empty for security
        );
    }
}
```

2. **Register in services.yaml**:

```yaml
services:
    App\HealthCheck\CustomCheck: ~
    # No manual tagging needed - autoconfigured via interface
```

## Commit Message Convention

Follow conventional commits:

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `test:` Adding or updating tests
- `refactor:` Code refactoring
- `perf:` Performance improvements
- `chore:` Maintenance tasks

Example: `feat: add MongoDB health check`

## Important Files

- `src/HealthCheck/HealthCheckInterface.php` - Contract with auto-tagging attribute
- `src/HealthCheck/AbstractHealthCheck.php` - Template Method pattern base class
- `src/Service/HealthCheckService.php` - Orchestrates all checks via tagged iterator
- `config/services.php` - Bundle service definitions
- `phpstan.neon` - PHPStan level 8 configuration
- `.php-cs-fixer.dist.php` - Code style rules
- `grumphp.yml` - Pre-commit hook configuration

## Common Pitfalls

1. **Metadata must always be empty** - Do not add diagnostic info to `metadata` array
2. **Use Yoda comparisons** - `true === $var` not `$var === true`
3. **Type hints everywhere** - All parameters and return types must have type hints
4. **Strict types** - Every PHP file must start with `declare(strict_types=1);`
5. **Generic messages** - Error messages must be generic, never include internal details
6. **Test locally with Docker** - Run `make check` before pushing to avoid CI failures
