# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial bundle implementation
- Health check interface and abstract base class
- Built-in health checks:
  - DatabaseHealthCheck (Doctrine DBAL)
  - RedisHealthCheck (phpredis/Predis)
  - HttpHealthCheck (external endpoints)
- HealthCheckService for aggregating checks
- HealthCheckController with /health endpoint
- Automatic service discovery via tags
- Timeout management and exception handling
- PHP 8.3+ features (enums, readonly, attributes)
- Comprehensive documentation

[Unreleased]: https://github.com/kiora/health-check-bundle/compare/v1.0.0...HEAD
