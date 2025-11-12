# Docker Setup Implementation

This document describes the Docker setup implemented for the Health Check Bundle.

## Overview

This implementation addresses [Issue #6](https://github.com/kiora-tech/health_check_bundle/issues/6) by adding comprehensive Docker support for both development and integration testing.

## Files Added

### 1. `Dockerfile`
- Based on PHP 8.3 CLI Alpine
- Includes all required PHP extensions:
  - PDO (MySQL, PostgreSQL)
  - Redis
  - MongoDB
  - Zip
  - Xdebug (for code coverage)
- Composer pre-installed
- Optimized for development with volume mounts

### 2. `docker-compose.yml` (Development)
- **PHP container**: Development environment with all tools
- **MySQL 8.0**: Database testing
- **PostgreSQL 16**: Alternative database testing
- **Redis 7**: Cache/session testing
- Volume mounts for live code editing
- Healthchecks for all services

### 3. `docker-compose.test.yml` (Integration Testing)
- All services from development setup
- **MongoDB 7**: NoSQL database testing
- **MinIO**: S3-compatible storage testing
- Uses tmpfs for faster test execution
- Auto-runs tests and exits
- Environment variables pre-configured

### 4. `.dockerignore`
- Optimizes Docker build by excluding unnecessary files
- Reduces image size and build time

### 5. `Makefile`
- Simplified commands for common tasks
- Quick start: `make up && make install && make test`
- All quality checks accessible via make commands
- Integration test commands included

## Usage

### Quick Start

```bash
# Start everything
make up && make install

# Run tests
make test

# Run all quality checks
make qa

# Open PHP shell
make shell

# Stop everything
make down
```

### Integration Testing

```bash
# Run integration tests with all services
make test-integration

# Build and run integration tests
make test-integration-build
```

### Manual Commands

```bash
# Start services
docker-compose up -d

# Install dependencies
docker-compose exec php composer install

# Run tests
docker-compose exec php composer test

# PHPStan
docker-compose exec php composer phpstan

# Code style fix
docker-compose exec php composer cs-fix

# All quality checks
docker-compose exec php composer qa

# Stop services
docker-compose down
```

## Services

All services are accessible from the PHP container and from localhost:

- **PHP**: Container name `health_check_bundle_php`
- **MySQL**: localhost:3306 (credentials: dev/dev)
- **PostgreSQL**: localhost:5432 (credentials: dev/dev)
- **Redis**: localhost:6379

## Integration Test Services

Available only in test environment (`docker-compose.test.yml`):

- **MySQL**: mysql-test:3306 (credentials: test/test)
- **PostgreSQL**: postgres-test:5432 (credentials: test/test)
- **Redis**: redis-test:6379
- **MongoDB**: mongodb-test:27017 (credentials: test/test)
- **MinIO**: minio-test:9000 (credentials: minioadmin/minioadmin)

## Benefits

1. **No local PHP installation required**: Everything runs in Docker
2. **Consistent environment**: Same versions for all developers
3. **Fast tests**: tmpfs for integration tests
4. **Easy CI/CD**: Ready for GitHub Actions
5. **Multiple databases**: Test against MySQL, PostgreSQL, MongoDB
6. **S3 testing**: MinIO for storage health checks
7. **Simplified workflow**: Makefile for common tasks

## Notes

- Volume mounts override container /app directory
- Dependencies must be installed after container starts: `make install`
- First run may take a few minutes to download images
- Integration tests clean up automatically after completion

## CI/CD Integration

### GitHub Actions Workflows

Three workflows have been configured:

#### 1. CI Workflow (`.github/workflows/ci.yml`)
- **Triggers**: Push and PR to main/develop branches
- **Matrix**: PHP 8.3 and 8.4
- **Runs**:
  - PHPStan (level 8)
  - PHP-CS-Fixer (check only)
  - PHPUnit tests
  - Code coverage upload (Codecov)

#### 2. Integration Tests (`.github/workflows/integration-tests.yml`)
- **Triggers**: Push, PR, and manual dispatch
- **Services**: MySQL, PostgreSQL, Redis, MongoDB, MinIO
- **Process**:
  1. Builds PHP test image
  2. Starts all test services
  3. Waits for services to be healthy
  4. Runs integration tests with real services
  5. Shows logs on failure
  6. Cleans up containers

#### 3. Symfony Compatibility (`.github/workflows/symfony-compatibility.yml`)
- **Triggers**: Push, PR, and weekly schedule (Monday 9 AM UTC)
- **Matrix**:
  - PHP: 8.3, 8.4
  - Symfony: 6.4, 7.0, 7.1, 7.2
- **Security**: Runs Symfony Security Checker

### Status Badges

The README includes badges for all workflows:
- [![CI](https://github.com/kiora-tech/health_check_bundle/workflows/CI/badge.svg)](https://github.com/kiora-tech/health_check_bundle/actions/workflows/ci.yml)
- [![Integration Tests](https://github.com/kiora-tech/health_check_bundle/workflows/Integration%20Tests/badge.svg)](https://github.com/kiora-tech/health_check_bundle/actions/workflows/integration-tests.yml)
- [![Symfony Compatibility](https://github.com/kiora-tech/health_check_bundle/workflows/Symfony%20Compatibility/badge.svg)](https://github.com/kiora-tech/health_check_bundle/actions/workflows/symfony-compatibility.yml)

## Future Improvements

- Add more integration test examples
- Consider adding Nginx container for HTTP health check testing
- Add database initialization scripts for integration tests
- Add mutation testing (Infection PHP)
- Add dependency update bot (Dependabot)
