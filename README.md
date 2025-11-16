# Health Check Bundle

A Symfony bundle providing comprehensive health check functionality for monitoring application dependencies and services.

[![CI](https://github.com/kiora-tech/health_check_bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/kiora-tech/health_check_bundle/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/kiora-tech/health_check_bundle/branch/main/graph/badge.svg)](https://codecov.io/gh/kiora-tech/health_check_bundle)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg?style=flat)](https://phpstan.org/)

[![Latest Stable Version](https://poser.pugx.org/kiora/health-check-bundle/v/stable)](https://packagist.org/packages/kiora/health-check-bundle)
[![Total Downloads](https://poser.pugx.org/kiora/health-check-bundle/downloads)](https://packagist.org/packages/kiora/health-check-bundle)
[![License](https://poser.pugx.org/kiora/health-check-bundle/license)](https://packagist.org/packages/kiora/health-check-bundle)

[![PHP Version](https://img.shields.io/badge/php-8.3%20%7C%208.4-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-6.4%20%7C%207.0%20%7C%207.1-green.svg?logo=symfony&logoColor=white)](https://symfony.com/)

## Why use this bundle?

### Production-Ready & Battle-Tested

- Comprehensive test coverage with 37 tests and 104 assertions
- PHPStan level 9 static analysis - strictest type safety (maximum level)
- CI/CD pipeline testing across PHP 8.3/8.4 and Symfony 6.4/7.0/7.1
- Security-first design with no sensitive information exposure

### Enterprise-Grade Features

- Multiple database connections support (read/write replicas, analytics, logs)
- Context-aware health checks with group filtering (web, worker, console)
- Kubernetes-ready with dedicated liveness (`/ping`) and readiness (`/ready`) probes
- Performance monitoring with execution statistics and slow check detection

### Developer-Friendly

- Modern auto-configuration with Symfony's `#[AutoconfigureTag]`
- No manual service tagging required
- Docker development environment included
- Follows Symfony best practices and coding standards

## Features

- 🔍 **Multiple Health Checks**: Database, Redis, S3/MinIO, HTTP endpoints
- 🗂️ **Multiple Connections**: Support for multiple database connections (read/write replicas, analytics, logs)
- 🏷️ **Check Groups**: Filter health checks by context (web, worker, console) via `?group=` parameter
- 🔒 **Security First**: No sensitive information exposed (versions, paths, credentials)
- ⚡ **Performance**: Configurable timeouts, non-blocking checks
- 🎯 **Flexible**: Critical vs non-critical checks, enable/disable per check
- 🛡️ **Production Ready**: Rate limiting, security headers, generic error messages
- 📊 **Standard Format**: JSON response with status, duration, and individual check results
- 📈 **Performance Statistics**: Monitor slow checks, average execution time, and identify performance bottlenecks

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

### Available Endpoints

The bundle provides three distinct endpoints for different monitoring purposes:

#### 1. `/ping` - Liveness Probe (Lightweight)

A simple endpoint that verifies the application is running without checking any external dependencies.

```bash
curl http://localhost/ping
```

**Response:**

```json
{
  "status": "up",
  "timestamp": "2024-01-01T12:00:00+00:00"
}
```

**Use case:** Kubernetes liveness probes, load balancer health checks

**Characteristics:**

- Always returns HTTP 200 (unless the app is completely down)
- No database or external service checks
- Extremely fast response time
- Minimal resource usage

#### 2. `/ready` - Readiness Probe (Critical Dependencies)

Checks if the application is ready to serve traffic by verifying critical dependencies in the "readiness" group.

```bash
curl http://localhost/ready
```

**Response:**

```json
{
  "status": "healthy",
  "timestamp": "2024-01-01T12:00:00+00:00",
  "duration": 0.015,
  "checks": [
    {
      "name": "database",
      "status": "healthy",
      "message": "Database operational",
      "duration": 0.012,
      "metadata": []
    }
  ],
  "statistics": {
    "total_checks": 1,
    "slow_checks": 0,
    "average_duration": 0.012,
    "slowest_check": {
      "name": "database",
      "duration": 0.012
    }
  }
}
```

**Use case:** Kubernetes readiness probes, determining when pods should receive traffic

**Characteristics:**

- Returns HTTP 200 if all critical dependencies are healthy
- Returns HTTP 503 if any critical dependency is unhealthy
- Only checks services in the "readiness" group
- Prevents traffic routing to pods with unhealthy dependencies

#### 3. `/health` - Comprehensive Health Check

Provides complete health status of all configured health checks, optionally filtered by group.

```bash
# Check all health checks
curl http://localhost/health

# Check specific group
curl http://localhost/health?group=web
```

**Use case:** Monitoring dashboards, alerting systems, comprehensive health status

**Characteristics:**

- Returns HTTP 200 if all critical checks pass
- Returns HTTP 503 if any critical check fails
- Supports filtering by group via `?group=` parameter
- Includes performance statistics

### Endpoint Comparison

| Feature | `/ping` | `/ready` | `/health` |
|---------|---------|----------|-----------|
| Purpose | Is app running? | Can serve traffic? | Overall health |
| Dependencies Checked | None | Readiness group only | All or filtered by group |
| Response Time | Instant | Fast | Depends on checks |
| Kubernetes Use | Liveness probe | Readiness probe | Monitoring |
| HTTP 503 on Failure | Never | Yes | Yes |
| Group Filtering | No | Fixed (readiness) | Yes (`?group=`) |

### Configuring Health Checks for Readiness

To mark health checks as critical for readiness probes, assign them to the "readiness" group:

```yaml
services:
    # Database - critical for readiness
    app.health_check.database:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\DatabaseHealthCheck
        autoconfigure: true
        arguments:
            $connection: '@doctrine.dbal.default_connection'
            $groups: ['readiness']  # Mark as critical for readiness
            $critical: true

    # Redis - critical for readiness
    app.health_check.redis:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\RedisHealthCheck
        autoconfigure: true
        arguments:
            $host: '%env(REDIS_HOST)%'
            $port: '%env(int:REDIS_PORT)%'
            $groups: ['readiness']  # Mark as critical for readiness
            $critical: true

    # External API - non-critical, not for readiness
    app.health_check.external_api:
        class: Kiora\HealthCheckBundle\HealthCheck\Checks\HttpHealthCheck
        autoconfigure: true
        arguments:
            $url: 'https://api.example.com/health'
            $name: 'external_api'
            $groups: ['web']  # Only in web group, not readiness
            $critical: false
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
  ],
  "statistics": {
    "total_checks": 2,
    "slow_checks": 0,
    "average_duration": 0.002,
    "slowest_check": {
      "name": "database",
      "duration": 0.002
    }
  }
}
```

#### Statistics Breakdown

The `statistics` section provides performance insights:

- **total_checks**: Total number of health checks executed
- **slow_checks**: Number of checks that took longer than 1 second to execute
- **average_duration**: Average execution time across all checks (in seconds)
- **slowest_check**: Details of the slowest health check
  - `name`: Name of the slowest check
  - `duration`: Execution time in seconds
  - Will be `null` if no checks were executed

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

### Kubernetes Liveness and Readiness Probes

Kubernetes distinguishes between two types of health checks:

- **Liveness Probe**: Determines if the pod is alive and should be restarted if not
- **Readiness Probe**: Determines if the pod is ready to receive traffic

#### Recommended Configuration

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: myapp
spec:
  template:
    spec:
      containers:
      - name: myapp
        image: myapp:latest
        ports:
        - containerPort: 80

        # Liveness probe - Is the pod alive?
        # Uses /ping endpoint (no external dependencies)
        livenessProbe:
          httpGet:
            path: /ping
            port: 80
            httpHeaders:
            - name: Host
              value: localhost
          initialDelaySeconds: 10
          periodSeconds: 10
          timeoutSeconds: 3
          failureThreshold: 3
          successThreshold: 1

        # Readiness probe - Can the pod serve traffic?
        # Uses /ready endpoint (checks critical dependencies)
        readinessProbe:
          httpGet:
            path: /ready
            port: 80
            httpHeaders:
            - name: Host
              value: localhost
          initialDelaySeconds: 5
          periodSeconds: 5
          timeoutSeconds: 5
          failureThreshold: 3
          successThreshold: 1
```

#### Probe Configuration Explained

**Liveness Probe (`/ping`):**

- `initialDelaySeconds: 10` - Wait 10 seconds after container starts before first check
- `periodSeconds: 10` - Check every 10 seconds
- `timeoutSeconds: 3` - Consider failed if no response within 3 seconds
- `failureThreshold: 3` - Restart pod after 3 consecutive failures
- Uses `/ping` endpoint which has no external dependencies and is extremely fast

**Readiness Probe (`/ready`):**

- `initialDelaySeconds: 5` - Wait 5 seconds after container starts before first check
- `periodSeconds: 5` - Check every 5 seconds
- `timeoutSeconds: 5` - Consider failed if no response within 5 seconds
- `failureThreshold: 3` - Remove from service after 3 consecutive failures
- Uses `/ready` endpoint which checks critical dependencies (database, cache, etc.)

#### Why Separate Endpoints?

**Problem with using `/health` for both:**

```yaml
# ❌ Not recommended - single endpoint for both probes
livenessProbe:
  httpGet:
    path: /health  # Checks all dependencies
    port: 80
readinessProbe:
  httpGet:
    path: /health  # Same checks
    port: 80
```

**Issues:**

1. If database is temporarily unavailable, pod gets restarted (liveness)
2. Unnecessary restarts can cause cascading failures
3. No distinction between "app is running" and "app can serve traffic"

**Solution with separate endpoints:**

```yaml
# ✅ Recommended - separate endpoints
livenessProbe:
  httpGet:
    path: /ping    # Only checks if app is alive
    port: 80
readinessProbe:
  httpGet:
    path: /ready   # Checks critical dependencies
    port: 80
```

**Benefits:**

1. Database issues remove pod from service (readiness) but don't restart it (liveness)
2. Pod has time to recover from transient failures
3. Clear separation of concerns
4. Follows Kubernetes best practices

#### Context-Specific Health Checks

For different deployment types, use the `?group=` parameter:

```yaml
# Web deployment
readinessProbe:
  httpGet:
    path: /health?group=web
    port: 80

# Worker deployment
readinessProbe:
  httpGet:
    path: /health?group=worker
    port: 80

# Or use dedicated /ready endpoint for critical dependencies
readinessProbe:
  httpGet:
    path: /ready  # Only checks "readiness" group
    port: 80
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

- **Readiness Probe Endpoint**: Added dedicated `/ready` endpoint for Kubernetes readiness probes
  - Checks only health checks in the "readiness" group
  - Returns HTTP 200 when ready, 503 when not ready
  - Proper separation between liveness (`/ping`) and readiness (`/ready`) probes
  - Follows Kubernetes best practices for probe configuration
  - Prevents traffic routing to pods with unhealthy critical dependencies
- **Multiple Database Connections**: Support for monitoring multiple Doctrine DBAL connections
  - Named connections with custom identifiers (e.g., `database_analytics`, `database_logs`)
  - Per-connection criticality configuration
  - Backward compatible with existing single connection setup
- **Health Check Groups**: Filter checks by context/group for granular monitoring
  - Query parameter support: `?group=web`, `?group=worker`, `?group=console`
  - Per-check group assignment
  - Empty groups = belongs to all contexts (default behavior)
- Comprehensive test suite with 37 tests and 104 assertions

### 1.0.0 (2025-11-06)

- Initial open source release
- Database, Redis, S3, and HTTP health checks
- Security-first design with no sensitive data exposure
- Modern auto-tagging with `#[AutoconfigureTag]` (Symfony 6.1+)
- Rate limiting support
- Configurable timeouts and critical checks
- Enable/disable checks via configuration
- Production-ready with Kubernetes/Docker support
