# Contributing to Health Check Bundle

Thank you for your interest in contributing to Health Check Bundle! We welcome contributions from the community.

## Development Setup

### Prerequisites

- PHP 8.3 or 8.4
- Composer
- Docker (recommended for testing)

### Installation

1. Fork and clone the repository:
```bash
git clone https://github.com/your-username/health_check_bundle.git
cd health_check_bundle
```

2. Install dependencies:
```bash
composer install
```

## Development Workflow

### Running Tests

Run the full test suite:
```bash
composer test
```

Run specific tests:
```bash
./vendor/bin/phpunit tests/HealthCheck/HealthCheckResultTest.php
```

### Code Quality

We maintain high code quality standards. Before submitting a PR, ensure your code passes:

#### PHPStan (Level 8)
```bash
composer phpstan
```

#### PHP-CS-Fixer
Check code style:
```bash
composer cs-check
```

Fix code style:
```bash
composer cs-fix
```

#### All Quality Checks
Run all checks at once:
```bash
composer check
```

## Pull Request Process

1. **Create a feature branch** from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following our coding standards

3. **Write tests** for new functionality

4. **Update documentation** if needed (README.md, inline comments)

5. **Run quality checks**:
   ```bash
   composer check
   ```

6. **Commit your changes** with clear, descriptive messages:
   ```bash
   git commit -m "feat: add new health check for RabbitMQ"
   ```

7. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```

8. **Create a Pull Request** with:
   - Clear title describing the change
   - Description of what changed and why
   - Reference to related issues (e.g., "Fixes #42")
   - Any breaking changes highlighted

## Commit Message Convention

We follow conventional commits:

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `test:` Adding or updating tests
- `refactor:` Code refactoring
- `perf:` Performance improvements
- `chore:` Maintenance tasks

Examples:
```
feat: add MongoDB health check
fix: correct timeout handling in Redis check
docs: update installation instructions
test: add integration tests for S3 check
```

## Coding Standards

### PHP Code Style

- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);`
- Type hints for all parameters and return types
- DocBlocks for all public methods
- Meaningful variable and method names

### Example

```php
<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

/**
 * Health check for RabbitMQ message broker.
 */
class RabbitMQHealthCheck extends AbstractHealthCheck
{
    /**
     * Check RabbitMQ connection health.
     */
    public function check(): HealthCheckResult
    {
        // Implementation
    }
}
```

### Testing

- Write unit tests for all new functionality
- Aim for high code coverage
- Use descriptive test method names
- Follow AAA pattern (Arrange, Act, Assert)

```php
public function testCheckReturnsHealthyWhenConnectionSucceeds(): void
{
    // Arrange
    $check = new DatabaseHealthCheck($this->connection);

    // Act
    $result = $check->check();

    // Assert
    $this->assertTrue($result->isHealthy());
}
```

## Adding New Health Checks

To add a new health check:

1. Create a class extending `AbstractHealthCheck`
2. Implement the `check()` method
3. Add configuration support in `Configuration.php`
4. Add service definition in `services.php`
5. Write comprehensive tests
6. Update README.md with usage example

## Questions or Need Help?

- 💬 Open a [GitHub Discussion](https://github.com/kiora-tech/health_check_bundle/discussions)
- 🐛 Report bugs via [GitHub Issues](https://github.com/kiora-tech/health_check_bundle/issues)
- 📧 Contact maintainers for security issues (see SECURITY.md)

## Code of Conduct

Please note that this project has a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to abide by its terms.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
