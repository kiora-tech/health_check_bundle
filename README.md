# Health Check Bundle

A Symfony bundle providing comprehensive health check functionality for monitoring application dependencies and services.

[![CI](https://github.com/kiora-tech/health_check_bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/kiora-tech/health_check_bundle/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E6.4%7C%5E7.0-green)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)

## Features

- 🔍 **Multiple Health Checks**: Database, Redis, S3/MinIO, HTTP endpoints
- 🗂️ **Multiple Connections**: Support for multiple database connections (read/write replicas, analytics, logs)
- 🏷️ **Check Groups**: Filter health checks by context (web, worker, console) via `?group=` parameter
- 🔒 **Security First**: No sensitive information exposed (versions, paths, credentials)
- ⚡ **Performance**: Configurable timeouts, non-blocking checks
- 🎯 **Flexible**: Critical vs non-critical checks, enable/disable per check
- 🛡️ **Production Ready**: Rate limiting, security headers, generic error messages
- 📊 **Standard Format**: JSON response with status, duration, and individual check results

## Development with Docker

The project includes a complete Docker environment for development and testing.

### Quick Start

```bash
# Start all services (MySQL, PostgreSQL, Redis, MinIO)
make up

# Install dependencies
make install

# Run tests
make test

# Run PHPStan analysis
make phpstan

# Check code style
make cs-check

# Fix code style
make cs-fix

# Run all quality checks
make check

# Open a shell in PHP container
make shell

# Stop all services
make down
```

### Available Services

- **PHP 8.4** with all required extensions
- **MySQL 8.0** on port 3306
- **PostgreSQL 16** on port 5432
- **Redis 7** on port 6379
- **MinIO (S3-compatible)** on ports 9000 (API) and 9002 (Console)

### Manual Commands

If you prefer not to use Make:

```bash
# Start services
docker compose up -d

# Run tests
docker compose exec php composer test

# Run PHPStan
docker compose exec php composer phpstan

# Open shell
docker compose exec php sh

# Stop services
docker compose down
```

## Installation

### 1. Install the bundle

```bash
composer require kiora/health-check-bundle
```

### 2. Enable the bundle

If Symfony Flex is not installed, manually add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Kiora\HealthCheckBundle\HealthCheckBundle::class => ['all' => true],
];
```

### 3. Import routes

Create `config/routes/health_check.yaml`:

```yaml
health_check_bundle:
    resource: '@HealthCheckBundle/config/routes.php'
    type: php
```

### 4. Configure security access

Add public access to the health check endpoint in `config/packages/security.yaml`:

```yaml
access_control:
    - { path: ^/health, roles: PUBLIC_ACCESS }
    # ... your other rules
```

### 5. (Optional) Configure rate limiting

Create `config/packages/rate_limiter.yaml`:

```yaml
framework:
    rate_limiter:
        health_check:
            policy: 'sliding_window'
            limit: 60
            interval: '1 minute'
```

Install required packages:

```bash
composer require symfony/rate-limiter symfony/lock
```

## Configuration

### Basic Configuration

Create `config/packages/health_check.yaml`:

```yaml
health_check:
    enabled: true
    checks:
        database:
            enabled: true  # Default: true (auto-registered)
        redis:
            enabled: false  # Default: false (enable only if Redis is available)
```

### Built-in Checks

#### 1. Database Check (Auto-registered)

✅ **Automatically enabled** - Verifies database connectivity using Doctrine DBAL.

```yaml
health_check:
    checks:
        database:
            enabled: true  # Default: true
```

No additional configuration needed. Works out of the box with your existing Doctrine configuration.

##### Multiple Database Connections

If your application uses multiple database connections (e.g., read/write replicas, analytics database, logs database), you can configure separate health checks for each connection:

```yaml
services:
    # Default connection (automatically registered)
    # Already available as 'database' check

    # Analytics database (read-only replica)
    app.health_check.database_analytics:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\DatabaseHealthCheck
        autoconfigure: true
        arguments:
            $connection: '@doctrine.dbal.analytics_connection'
            $name: 'analytics'
            $critical: false  # Non-critical: analytics can be down without affecting main app
            $groups: ['worker', 'cron']  # Only check in worker/cron contexts

    # Logs database
    app.health_check.database_logs:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\DatabaseHealthCheck
        autoconfigure: true
        arguments:
            $connection: '@doctrine.dbal.logs_connection'
            $name: 'logs'
            $critical: false  # Non-critical: logging can fail without affecting main app
            $groups: ['web', 'worker']  # Check in both web and worker contexts
```

**Naming Convention:**
- Default connection: Returns `database`
- Named connections: Returns `database_{name}` (e.g., `database_analytics`, `database_logs`)

**Configuration Options:**
- `$name`: Connection identifier (default: `'default'`)
- `$critical`: Whether failure should return HTTP 503 (default: `true`)
- `$groups`: Contexts where this check runs (default: `[]` = all contexts)

#### 2. Redis Check (Manual setup)

⚙️ **Disabled by default** - Only configure if your project uses Redis.

**Step 1:** Enable in configuration

```yaml
health_check:
    checks:
        redis:
            enabled: true  # Explicitly enable Redis check
```

**Step 2:** Configure the service

Create or update `config/packages/health_check.yaml`:

```yaml
services:
    Kiora\HealthCheckBundle\HealthCheck\Checks\RedisHealthCheck:
        autoconfigure: true  # Enables auto-tagging via interface
        arguments:
            $host: '%env(REDIS_HOST)%'
            $port: '%env(int:REDIS_PORT)%'
            $critical: false  # Set to true if Redis failure should return 503
```

**Step 3:** Add environment variables

```env
REDIS_HOST=redis
REDIS_PORT=6379
```

#### 3. S3/MinIO Storage Check (Manual setup)

⚙️ **Requires manual configuration** - Only configure if your project uses S3/MinIO storage.

```yaml
services:
    app.health_check.s3_storage:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\S3HealthCheck
        autoconfigure: true  # Enables auto-tagging via interface
        arguments:
            $filesystem: '@your.flysystem.storage'  # Your Flysystem service ID
            $name: 's3_storage'
            $critical: false
```

**Example for multiple buckets:**

```yaml
services:
    # Documents storage
    app.health_check.s3_documents:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\S3HealthCheck
        autoconfigure: true  # Enables auto-tagging via interface
        arguments:
            $filesystem: '@documents.storage'
            $name: 's3_documents'
            $critical: false

    # Templates storage
    app.health_check.s3_templates:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\S3HealthCheck
        autoconfigure: true  # Enables auto-tagging via interface
        arguments:
            $filesystem: '@templates.storage'
            $name: 's3_templates'
            $critical: false
```

#### 4. HTTP Endpoint Check (Manual setup)

⚙️ **For monitoring external dependencies** - Configure for each external API you depend on.

```yaml
services:
    app.health_check.external_api:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\HttpHealthCheck
        autoconfigure: true  # Enables auto-tagging via interface
        arguments:
            $url: 'https://api.example.com/health'
            $name: 'external_api'
            $timeout: 5
            $critical: false
            $expectedStatusCodes: [200, 401]  # 401 = API accessible but auth required
```

#### Example: Microsoft Graph API

```yaml
services:
    app.health_check.microsoft_graph:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\HttpHealthCheck
        autoconfigure: true  # Enables auto-tagging via interface
        arguments:
            $url: 'https://graph.microsoft.com/v1.0/me'
            $name: 'microsoft_graph'
            $timeout: 5
            $critical: false
            $expectedStatusCodes: [401]  # 401 = API accessible, auth required (expected)
```

## Usage

### Accessing the Health Check Endpoint

```bash
curl http://localhost/health
```

### Response Format

```json
{
  "status": "healthy",
  "timestamp": "2025-11-05T18:02:20+01:00",
  "duration": 0.015,
  "checks": [
    {
      "name": "database",
      "status": "healthy",
      "message": "Database operational",
      "duration": 0.002,
      "metadata": []
    },
    {
      "name": "redis",
      "status": "healthy",
      "message": "Redis operational",
      "duration": 0.001,
      "metadata": []
    }
  ]
}
```

### Status Codes

- **200 OK**: All critical checks passed
- **503 Service Unavailable**: One or more critical checks failed

### Check Status Values

- `healthy`: Check passed successfully
- `unhealthy`: Check failed
- `degraded`: Check passed with warnings (reserved for future use)

### Filtering Checks by Group

Health checks can be organized into groups/contexts (e.g., `web`, `worker`, `console`) to enable granular monitoring based on the application context.

#### Using the Group Query Parameter

Filter health checks by group using the `?group=` query parameter:

```bash
# Check only web-related services
curl http://localhost/health?group=web

# Check only worker/background job services
curl http://localhost/health?group=worker

# Check only console/CLI services
curl http://localhost/health?group=console
```

#### Configuring Groups

Assign groups to health checks in your service configuration:

```yaml
services:
    # Database check - runs in all contexts (no groups specified)
    Kiora\HealthCheckBundle\HealthCheck\Checks\DatabaseHealthCheck:
        autoconfigure: true
        arguments:
            $connection: '@doctrine.dbal.default_connection'
            $name: 'default'
            $critical: true
            $groups: []  # Empty = belongs to all groups

    # Redis check - only for web and worker contexts
    Kiora\HealthCheckBundle\HealthCheck\Checks\RedisHealthCheck:
        autoconfigure: true
        arguments:
            $host: '%env(REDIS_HOST)%'
            $port: '%env(int:REDIS_PORT)%'
            $critical: false
            $groups: ['web', 'worker']  # Only runs when ?group=web or ?group=worker

    # External API check - only for web context
    app.health_check.external_api:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\HttpHealthCheck
        autoconfigure: true
        arguments:
            $url: 'https://api.example.com/health'
            $name: 'external_api'
            $timeout: 5
            $critical: false
            $expectedStatusCodes: [200]
            $groups: ['web']  # Only runs when ?group=web
```

#### Group Filtering Behavior

- **Empty groups** (`$groups: []`): Check runs in **all contexts** (no filtering)
- **Specified groups** (`$groups: ['web', 'worker']`): Check runs only when requested group matches
- **No group parameter**: All checks run (default behavior)

#### Use Cases

**Kubernetes Probes:**
```yaml
# Web pod - check only web-related services
livenessProbe:
  httpGet:
    path: /health?group=web
    port: 80

# Worker pod - check only worker-related services
livenessProbe:
  httpGet:
    path: /health?group=worker
    port: 80
```

**Console Commands:**
```bash
# Check services needed for console commands
php bin/console app:health-check --group=console
```

**Monitoring Different Environments:**
```bash
# Production web servers - check critical web services
curl https://prod.example.com/health?group=web

# Background workers - check queue and batch processing services
curl https://worker.example.com/health?group=worker
```

## Creating Custom Health Checks

### 1. Create your check class

Simply implement `HealthCheckInterface` or extend `AbstractHealthCheck`. **No manual tagging required!**

```php
<?php

namespace App\HealthCheck;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;

class CustomHealthCheck extends AbstractHealthCheck
{
    public function __construct(
        private readonly bool $critical = false,
        private readonly array $groups = []
    ) {
    }

    public function getName(): string
    {
        return 'custom';
    }

    public function getTimeout(): int
    {
        return 5; // seconds
    }

    public function isCritical(): bool
    {
        return $this->critical;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    protected function doCheck(): HealthCheckResult
    {
        try {
            // Your health check logic here
            $isHealthy = true;

            return new HealthCheckResult(
                name: $this->getName(),
                status: $isHealthy ? HealthCheckStatus::HEALTHY : HealthCheckStatus::UNHEALTHY,
                message: $isHealthy ? 'Custom check passed' : 'Custom check failed',
                duration: 0.0,
                metadata: [] // Always empty for security
            );
        } catch (\Exception $e) {
            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::UNHEALTHY,
                message: 'Custom check failed',
                duration: 0.0,
                metadata: []
            );
        }
    }
}
```

### 2. Register your check

If you have `autowire: true` and `autoconfigure: true` in your `services.yaml` (default in modern Symfony):

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Simple registration (uses defaults: non-critical, no groups)
    App\HealthCheck\CustomHealthCheck: ~

    # With configuration
    App\HealthCheck\CustomHealthCheck:
        arguments:
            $critical: true  # Mark as critical
            $groups: ['web', 'worker']  # Specify groups
```

**How it works**: All classes implementing `HealthCheckInterface` are automatically tagged with `health_check.checker` thanks to the `#[AutoconfigureTag]` attribute on the interface. No manual tagging needed!

**Configuration Options:**
- `$critical`: Set to `true` if this check's failure should return HTTP 503 (default: `false`)
- `$groups`: Array of group names this check belongs to (default: `[]` = all groups)

## Security Considerations

### What's Included ✅

- **Generic error messages** - No stack traces or internal paths
- **No version information** - Database/Redis versions not exposed
- **No connection details** - Hostnames, IPs, credentials hidden
- **Security headers** - X-Robots-Tag, Cache-Control, X-Content-Type-Options
- **Rate limiting support** - Prevent abuse and DoS attacks
- **Empty metadata** - No sensitive information in responses

### What's NOT Included ❌

- Database names or connection strings
- Server versions or software versions
- File paths or directory structures
- Exception messages with sensitive data
- Response sizes or client counts
- Internal URLs or endpoints

### Best Practices

1. **Enable rate limiting** to prevent abuse
2. **Use IP whitelisting** for production environments if possible
3. **Monitor access logs** to detect reconnaissance attempts
4. **Keep checks non-critical** unless absolutely necessary
5. **Avoid exposing internal service names** in check names

## Monitoring Integration

### Kubernetes Liveness/Readiness Probes

```yaml
livenessProbe:
  httpGet:
    path: /health
    port: 80
  initialDelaySeconds: 30
  periodSeconds: 10

readinessProbe:
  httpGet:
    path: /health
    port: 80
  initialDelaySeconds: 5
  periodSeconds: 5
```

### Docker Healthcheck

```dockerfile
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD curl -f http://localhost/health || exit 1
```

### Prometheus/Grafana

The JSON response can be easily parsed and converted to metrics for monitoring dashboards.

## Troubleshooting

### Health check returns 404

- Verify routes are imported in `config/routes/health_check.yaml`
- Clear cache: `php bin/console cache:clear`
- Check bundle is enabled in `config/bundles.php`

### Check always returns "unhealthy"

- Verify service configuration (host, port, credentials)
- Check Docker network connectivity between services
- Review application logs for connection errors
- Ensure the service is actually running

### Rate limiting errors

- Install required packages: `composer require symfony/rate-limiter symfony/lock`
- Configure rate limiter in `config/packages/rate_limiter.yaml`

### Redis check not working

- Ensure Redis service is running
- Verify Redis is on the same Docker network as your application
- Check `REDIS_HOST` and `REDIS_PORT` environment variables
- Test connection: `docker compose exec php php -r "..."`

## Requirements

- PHP >= 8.2
- Symfony >= 6.4 or >= 7.0
- Doctrine DBAL (for database check)

## Optional Dependencies

- `ext-redis`: For RedisHealthCheck with PHP Redis extension
- `predis/predis`: Alternative Redis client
- `league/flysystem`: For S3HealthCheck
- `symfony/http-client`: For better HTTP check performance
- `symfony/rate-limiter`: For rate limiting support
- `symfony/lock`: Required by rate limiter

## Configuration Reference

### Complete Example

```yaml
# config/packages/health_check.yaml
health_check:
    enabled: true
    checks:
        database:
            enabled: true  # Default: true
        redis:
            enabled: true  # Default: false - explicitly enable if Redis is available

services:
    # Redis Health Check (only if enabled above)
    Kiora\HealthCheckBundle\HealthCheck\Checks\RedisHealthCheck:
        autoconfigure: true
        arguments:
            $host: '%env(REDIS_HOST)%'
            $port: '%env(int:REDIS_PORT)%'
            $critical: false

    # S3 Storage Health Check
    app.health_check.s3:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\S3HealthCheck
        autoconfigure: true
        arguments:
            $filesystem: '@documents.storage'
            $name: 's3_documents'
            $critical: false

    # External API Health Check
    app.health_check.api:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\HttpHealthCheck
        autoconfigure: true
        arguments:
            $url: 'https://api.example.com/health'
            $name: 'external_api'
            $timeout: 5
            $critical: false
            $expectedStatusCodes: [200]
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup

```bash
git clone https://github.com/kiora-tech/health_check_bundle.git
cd health_check_bundle
composer install
```

### Code Quality

This project uses GrumPHP to ensure code quality. All checks are run automatically before each commit:

```bash
# Run all quality checks (PHPStan, PHP-CS-Fixer, PHPUnit)
composer qa

# Run specific checks
composer test          # PHPUnit tests
composer phpstan       # PHPStan static analysis
composer cs-check      # PHP-CS-Fixer check (dry-run)
composer cs-fix        # PHP-CS-Fixer fix
```

### Coding Standards

- **PSR-12**: Follow PSR-12 coding standards
- **Strict types**: All files must declare `strict_types=1`
- **Type hints**: Use type hints for all method parameters and return types
- **PHPStan Level 8**: Code must pass PHPStan level 8 analysis
- **Tests**: Add tests for new features and bug fixes

## Support

- **Issues**: [GitHub Issues](https://github.com/kiora-tech/health_check_bundle/issues)
- **Documentation**: [README.md](https://github.com/kiora-tech/health_check_bundle/blob/main/README.md)

## License

This bundle is released under the [MIT License](LICENSE).

## Credits

Created and maintained by [Kiora Tech](https://kiora.tech).

## Changelog

### Unreleased

- **Multiple Database Connections**: Support for monitoring multiple Doctrine DBAL connections
  - Named connections with custom identifiers (e.g., `database_analytics`, `database_logs`)
  - Per-connection criticality configuration
  - Backward compatible with existing single connection setup
- **Health Check Groups**: Filter checks by context/group for granular monitoring
  - Query parameter support: `?group=web`, `?group=worker`, `?group=console`
  - Per-check group assignment
  - Empty groups = belongs to all contexts (default behavior)
- Comprehensive test suite with 31 tests and 86 assertions

### 1.0.0 (2025-11-06)

- Initial open source release
- Database, Redis, S3, and HTTP health checks
- Security-first design with no sensitive data exposure
- Modern auto-tagging with `#[AutoconfigureTag]` (Symfony 6.1+)
- Rate limiting support
- Configurable timeouts and critical checks
- Enable/disable checks via configuration
- Production-ready with Kubernetes/Docker support
