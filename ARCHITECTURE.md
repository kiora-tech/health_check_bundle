# Architecture & Design Decisions

## Overview

This bundle implements a modular, extensible health check system for Symfony applications using modern PHP 8.3+ features and best practices.

## Core Design Principles

### 1. Interface Segregation
All health checks implement `HealthCheckInterface`, ensuring consistent behavior across different check types.

### 2. Open/Closed Principle
The bundle is open for extension (new health checks) but closed for modification (core logic is stable).

### 3. Dependency Injection
All services are autowired and autoconfigured. Health checks are discovered automatically using the `health_check.checker` tag.

### 4. Single Responsibility
Each class has a single, well-defined responsibility:
- `HealthCheckInterface`: Contract for health checks
- `AbstractHealthCheck`: Timeout and error handling
- `HealthCheckService`: Aggregates and orchestrates checks
- `HealthCheckController`: HTTP endpoint
- Concrete checks: Specific system verification

## Component Architecture

### Value Objects

#### HealthCheckStatus (Enum)
```php
enum HealthCheckStatus: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNHEALTHY = 'unhealthy';
}
```

**Design Decisions:**
- Uses PHP 8.3 enum for type safety
- Backed by string for easy JSON serialization
- Includes helper methods (`isHealthy()`, `getHttpStatusCode()`)
- Immutable and predictable

#### HealthCheckResult (Readonly Class)
```php
readonly class HealthCheckResult
{
    public function __construct(
        public string $name,
        public HealthCheckStatus $status,
        public string $message,
        public float $duration,
        public array $metadata = []
    ) {}
}
```

**Design Decisions:**
- Readonly ensures immutability
- Typed properties for reliability
- Includes `toArray()` for serialization
- Metadata array for extensibility

### Service Layer

#### HealthCheckInterface
Defines the contract for all health checks:
- `check()`: Execute the health check
- `getName()`: Unique identifier
- `getTimeout()`: Maximum execution time
- `isCritical()`: Affects overall health status

#### AbstractHealthCheck
Provides common functionality:
- **Timeout Management**: Uses `set_time_limit()` to enforce timeouts
- **Duration Measurement**: Automatic timing with `microtime(true)`
- **Exception Handling**: Catches all throwables and converts to results
- **Template Method Pattern**: Subclasses implement `doCheck()`

**Why Abstract Class?**
- Provides concrete implementations of common logic
- Enforces consistent behavior across all checks
- Reduces boilerplate in concrete implementations

#### Built-in Health Checks

##### DatabaseHealthCheck
- Executes `SELECT 1` to verify connection
- Reports database driver and version
- Critical by default (5s timeout)

##### RedisHealthCheck
- Sends PING command
- Supports both phpredis and Predis
- Reports server stats if available
- Critical by default (3s timeout)

##### HttpHealthCheck
- Verifies external endpoint accessibility
- Configurable URL, timeout, status codes
- Non-critical by default (customizable)
- Uses native PHP streams (no dependencies)

### Service Orchestration

#### HealthCheckService
```php
class HealthCheckService
{
    public function __construct(
        private readonly iterable $healthChecks
    ) {}
}
```

**Design Decisions:**
- Uses tagged iterator for automatic service discovery
- Determines overall status: unhealthy if any critical check fails
- Provides both aggregate and individual check execution
- Returns structured array for easy JSON serialization

### Controller Layer

#### HealthCheckController
```php
#[Route('/health', name: 'health_check', methods: ['GET'])]
public function check(): JsonResponse
```

**Design Decisions:**
- Uses PHP attributes for routing
- Returns HTTP 200 for healthy, 503 for unhealthy
- Follows RESTful conventions
- Minimal logic (delegates to service)

## Dependency Injection

### Service Discovery Pattern

```php
// In services.php
$services->set(HealthCheckService::class)
    ->arg('$healthChecks', tagged_iterator('health_check.checker'));

// In health checks
#[AutoconfigureTag('health_check.checker')]
class DatabaseHealthCheck extends AbstractHealthCheck
```

**Benefits:**
- Zero configuration for new health checks
- Automatic registration via attributes
- No manual service wiring required

### Compiler Pass

`HealthCheckPass` collects all tagged services and injects them into `HealthCheckService`.

**Why a Compiler Pass?**
- Provides explicit control over service collection
- Fallback if tagged_iterator is not available
- Allows for additional processing of checks

## Configuration System

### Extension
`HealthCheckExtension` loads services and processes configuration.

**Custom Alias:**
```php
public function getAlias(): string
{
    return 'health_check';
}
```

Allows YAML configuration:
```yaml
health_check:
    enabled: true
    checks:
        database:
            enabled: true
```

### Configuration Tree
`Configuration` class defines available options:
- Global enable/disable
- Per-check configuration (future expansion)

## Error Handling Strategy

### Timeout Protection
```php
$previousTimeout = ini_get('max_execution_time');
set_time_limit($timeout);
try {
    // Execute check
} finally {
    set_time_limit((int) $previousTimeout);
}
```

**Benefits:**
- Prevents hanging health checks
- Restores previous timeout setting
- Graceful degradation

### Exception Handling
All exceptions are caught and converted to `UNHEALTHY` results with diagnostic metadata.

**Metadata Includes:**
- Exception class
- File and line number
- Error message

## Extensibility Points

### Creating Custom Checks

**Option 1: Extend AbstractHealthCheck**
```php
#[AutoconfigureTag('health_check.checker')]
class CustomCheck extends AbstractHealthCheck
{
    protected function doCheck(): HealthCheckResult
    {
        // Your logic here
    }
}
```

**Option 2: Implement HealthCheckInterface**
```php
#[AutoconfigureTag('health_check.checker')]
class CustomCheck implements HealthCheckInterface
{
    // Full control over implementation
}
```

### Future Extensibility

The architecture supports:
- Custom result formatters
- Event dispatching (before/after checks)
- Check scheduling
- Result caching
- Notification on failures
- Health check dependencies

## Performance Considerations

### Parallel Execution (Future)
Current implementation runs checks sequentially. Future versions could:
- Use Symfony Messenger for async checks
- Implement parallel execution with Fibers
- Cache results for high-frequency requests

### Caching Strategy
Consider caching health check results:
```php
$cache->get('health_check', function() {
    return $this->healthCheckService->runAllChecks();
}, ttl: 60);
```

## Security Considerations

### Information Disclosure
Health check endpoints may expose:
- Database driver and version
- Redis server info
- External service URLs

**Recommendations:**
- Restrict /health endpoint to internal networks
- Use Symfony security to require authentication
- Filter sensitive metadata in production

### Rate Limiting
Consider rate limiting the /health endpoint to prevent abuse.

## Testing Strategy (Future)

### Unit Tests
- Test each health check in isolation
- Mock external dependencies
- Verify timeout enforcement
- Test exception handling

### Integration Tests
- Verify service registration
- Test controller responses
- Validate JSON structure
- Check HTTP status codes

### Functional Tests
- Test with real database
- Test with real Redis
- Test HTTP endpoint checks
- Verify overall status calculation

## Versioning & Compatibility

### Symfony Compatibility
- Supports Symfony 6.4+ and 7.x
- Uses standard bundle structure
- Follows Symfony best practices

### PHP Requirements
- Requires PHP 8.3+ for:
  - Readonly classes
  - Enum types
  - Attributes
  - Typed properties

### Backward Compatibility
- Value object immutability ensures API stability
- Interface-based design allows gradual migration
- Semantic versioning for releases

## Related Patterns

### Template Method Pattern
`AbstractHealthCheck` uses template method:
```php
final public function check(): HealthCheckResult
{
    // Common logic
    $result = $this->doCheck(); // Template method
    // More common logic
}
```

### Strategy Pattern
Each health check is a strategy for verifying a specific system component.

### Registry Pattern
`HealthCheckService` acts as a registry of available health checks.

## Metrics & Monitoring

### Exposed Metrics
Each health check result includes:
- Execution duration
- Status (healthy/degraded/unhealthy)
- Custom metadata

### Integration Opportunities
- Prometheus metrics exporter
- Application Performance Monitoring (APM)
- Log aggregation (ELK, Graylog)
- Alerting systems

## Bundle Distribution

### Composer Package
```json
{
    "type": "symfony-bundle",
    "name": "kiora/health-check-bundle",
    "autoload": {
        "psr-4": {
            "Kiora\\HealthCheckBundle\\": "src/"
        }
    }
}
```

### Installation Methods
1. **Path Repository** (development)
2. **Private Packagist** (internal distribution)
3. **Public Packagist** (open source)

## Conventions

### Naming
- Health check names: lowercase with underscores (`database`, `redis_cache`)
- Class names: PascalCase with suffix (`DatabaseHealthCheck`)
- Service IDs: Fully qualified class names

### Coding Standards
- PSR-12 code style
- Strict types declaration
- PHPDoc for public APIs
- Type hints for all parameters and returns
