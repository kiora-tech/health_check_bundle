# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony bundle that provides comprehensive health check functionality for monitoring application dependencies and services. It's designed with security-first principles: no sensitive information (versions, paths, credentials) is exposed in health check responses.

**Package name:** `kiora/health-check-bundle`
**Namespace:** `Kiora\HealthCheckBundle`

## Development Commands

### Docker Setup (Recommended)

If PHP is not installed locally, use Docker for development:

```bash
# Start environment and install dependencies
make up && make install

# Common commands
make test          # Run tests
make phpstan       # Static analysis
make cs-fix        # Fix code style
make qa            # All quality checks
make shell         # Open PHP container shell
make down          # Stop environment

# Integration tests
make test-integration       # Run integration tests with all services
```

### Local Development (PHP 8.3+ required)

```bash
# Run all quality checks (PHPStan, PHP-CS-Fixer, PHPUnit)
composer qa

# Run specific checks
composer test          # PHPUnit tests
composer phpstan       # PHPStan static analysis (level 8)
composer cs-check      # PHP-CS-Fixer check (dry-run)
composer cs-fix        # PHP-CS-Fixer fix
```

**Important:** GrumPHP runs automatically on git commit and executes all quality checks.

### Testing
```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/HealthCheck/HealthCheckResultTest.php

# Run with coverage (requires Xdebug/PCOV)
./vendor/bin/phpunit --coverage-html coverage
```

### Static Analysis
```bash
# PHPStan analysis (level 8)
./vendor/bin/phpstan analyse

# With specific configuration
./vendor/bin/phpstan analyse -c phpstan.neon
```

## Architecture

### Core Design Principles

1. **Auto-tagging via Attributes**: All classes implementing `HealthCheckInterface` are automatically tagged with `health_check.checker` using Symfony's `#[AutoconfigureTag]` attribute (Symfony 6.1+). No manual service tagging required.

2. **Tagged Iterator Pattern**: `HealthCheckService` receives all health checks via Symfony's `tagged_iterator()` mechanism, allowing dynamic registration without modifying core service definitions.

3. **Compiler Pass for Configuration**: `HealthCheckPass` processes configuration to enable/disable built-in checks (database, Redis) before the container is compiled.

4. **Security-First**: All health check responses use generic messages with no stack traces, versions, or connection details. Metadata is always empty.

### Component Hierarchy

```
HealthCheckBundle (extends AbstractBundle)
├── DependencyInjection/
│   ├── HealthCheckExtension          # Loads services, processes config
│   ├── Configuration                 # Defines config tree (health_check.*)
│   └── Compiler/
│       └── HealthCheckPass           # Removes disabled checks from container
├── HealthCheck/
│   ├── HealthCheckInterface          # Core interface with #[AutoconfigureTag]
│   ├── AbstractHealthCheck           # Base class with timeout/error handling
│   ├── HealthCheckResult             # Immutable result object
│   ├── HealthCheckStatus (enum)      # HEALTHY, UNHEALTHY, DEGRADED
│   └── Checks/
│       ├── DatabaseHealthCheck       # Auto-registered (enabled by default)
│       ├── RedisHealthCheck          # Manual setup (disabled by default)
│       ├── S3HealthCheck             # Manual setup (Flysystem required)
│       └── HttpHealthCheck           # Manual setup (for external APIs)
├── Service/
│   └── HealthCheckService            # Aggregates and executes all checks
├── Controller/
│   └── HealthCheckController         # /health endpoint
└── config/
    ├── services.php                  # Service definitions
    └── routes.php                    # Route definitions
```

### How Health Checks Work

1. **Registration**: All classes implementing `HealthCheckInterface` are automatically tagged with `health_check.checker` via the `#[AutoconfigureTag]` attribute on the interface.

2. **Configuration Processing**: During container compilation, `HealthCheckPass` reads the `health_check.checks.*` configuration and removes disabled checks from the container.

3. **Injection**: `HealthCheckService` receives all enabled health checks via `tagged_iterator('health_check.checker')`.

4. **Execution**: When `/health` is hit:
   - `HealthCheckController` calls `HealthCheckService::runAllChecks()`
   - Each check's `check()` method is executed (with timeout management in `AbstractHealthCheck`)
   - Results are aggregated with overall status determined by critical check failures
   - JSON response is returned with 200 (healthy) or 503 (unhealthy)

### Built-in Check Behavior

- **DatabaseHealthCheck**: Automatically registered and enabled by default. Uses Doctrine DBAL to execute `SELECT 1`.
- **RedisHealthCheck**: Automatically tagged via interface, but removed by compiler pass unless explicitly enabled in config.
- **S3HealthCheck**: Not auto-registered. Requires manual service definition with Flysystem dependency.
- **HttpHealthCheck**: Not auto-registered. Requires manual service definition per external endpoint.

### Critical vs Non-Critical Checks

- **Critical checks** (`isCritical() === true`): If failed, overall status becomes UNHEALTHY and HTTP 503 is returned.
- **Non-critical checks** (`isCritical() === false`): Failures don't affect overall status, useful for degraded dependencies.

## Coding Standards

- **PHP Version**: 8.3+ (strict_types=1 required in all files)
- **Symfony Version**: 6.4+ or 7.0+
- **PSR-12**: Enforced via PHP-CS-Fixer
- **PHPStan Level 8**: All code must pass strict static analysis
- **Type Safety**: All parameters and return types must be explicitly typed
- **Immutability**: Prefer readonly properties and immutable objects (e.g., `HealthCheckResult`)

### Important Patterns

1. **Constructor Property Promotion**: Use for readonly dependencies
   ```php
   public function __construct(
       private readonly Connection $connection
   ) {}
   ```

2. **Final Methods**: The `check()` method in `AbstractHealthCheck` is `final` to ensure timeout/error handling is always applied.

3. **Template Method Pattern**: `AbstractHealthCheck::check()` wraps `doCheck()`, allowing subclasses to implement only the check logic.

4. **Named Arguments**: Used consistently in `HealthCheckResult` construction for clarity.

## Creating Custom Health Checks

To add a new health check:

1. **Extend `AbstractHealthCheck`** (recommended) or implement `HealthCheckInterface` directly.
2. **Implement required methods**: `getName()`, `getTimeout()`, `isCritical()`, and `doCheck()`.
3. **Register the service** in your application's `services.yaml` (auto-tagging happens automatically via interface).
4. **Return generic messages**: Never expose sensitive information in messages or metadata.

Example:
```php
namespace App\HealthCheck;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;

class CustomHealthCheck extends AbstractHealthCheck
{
    public function getName(): string
    {
        return 'custom_service';
    }

    public function getTimeout(): int
    {
        return 5;
    }

    public function isCritical(): bool
    {
        return false;
    }

    protected function doCheck(): HealthCheckResult
    {
        // Check logic here
        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::HEALTHY,
            message: 'Service operational',
            duration: 0.0,  // Auto-calculated by AbstractHealthCheck
            metadata: []    // Always empty for security
        );
    }
}
```

## Configuration System

The bundle uses Symfony's configuration component with a two-tier system:

1. **Bundle-level config** (`health_check.enabled`, `health_check.checks.*`): Controls which built-in checks are registered.
2. **Service-level config**: Individual health check services can be configured with custom arguments (host, port, timeout, critical, etc.).

The compiler pass (`HealthCheckPass`) reads bundle configuration and removes disabled checks before the container is compiled. This means disabled checks are truly not loaded, not just skipped at runtime.

## Security Considerations

- **Never expose**: versions, hostnames, IPs, ports, credentials, paths, exception details.
- **Always return**: generic messages like "Database operational" or "Service failed".
- **Metadata**: Always return empty array, even if tempted to add debug info.
- **Rate limiting**: Configured externally via `framework.rate_limiter.health_check`.
- **Security headers**: Automatically added by controller (X-Robots-Tag, Cache-Control, etc.).

## Testing Guidelines

- All health checks should have unit tests covering:
  - Successful check execution
  - Failure scenarios
  - Timeout behavior
  - Exception handling
- Use PHPUnit 10+ data providers for testing multiple scenarios
- Mock external dependencies (databases, Redis, HTTP clients, filesystems)
- Test that no sensitive information leaks in failure messages
