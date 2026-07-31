# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **Criticality was attributed to the wrong check when filtering by group.**
  `HealthCheckService` correlated results back to checks by array position,
  which breaks as soon as a group filter skips one. A failing non-critical
  check could return HTTP 503, and a failing critical check could be reported
  as healthy. Criticality is now captured together with each result.
- `/ping` and `/ready` were unreachable when importing the bundle's routing
  file: only `/health` was declared, and `PingController` was not registered
  as a service.
- `S3HealthCheck` called `toArray()` on the bucket listing, paging through and
  holding every object in memory. It now consumes a single entry.
- `DatabaseHealthCheck` issued a bare `SELECT 1`, which is a syntax error on
  Oracle and DB2. It now uses the DBAL platform's sentinel query.
- `HealthCheckPass` read the `health_check.checks` parameter without checking
  it exists, and matched built-in checks on a substring of the service id,
  which could disable unrelated application services.
- Health checks are injected as a lazy iterator again; the compiler pass had
  replaced the tagged iterator with a plain array, instantiating every check
  and its connections up front.
- GrumPHP ran PHPStan without a memory limit (crashing the pre-commit hook) and
  pinned level 8 while `phpstan.neon` declared level 9.

### Added

- Optional PSR-3 logging in `AbstractHealthCheck`. Failures are recorded
  server-side with their exception; HTTP responses stay generic.
- Timeout overruns are detected and reported as degraded. `set_time_limit()`
  cannot interrupt blocking I/O, so the declared timeout is verified after the
  fact rather than assumed to have been enforced.
- `HttpHealthCheck` accepts a Symfony `HttpClientInterface`. The stream-wrapper
  fallback no longer downloads the response body and understands HTTP/2 status
  lines.
- `RedisHealthCheck` accepts a pre-configured `\Redis` client, so applications
  can probe the connection they actually use.
- `getHealthStatus()` accepts a `$group` argument and reuses the result cache.
- Health check ordering via the `priority` attribute on the tag.
- CI covers Symfony 8.x, PHP 8.5, and a lowest-dependencies run.
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

### Changed

- **The overall status can now be `degraded`.** Previously a degraded check, or
  a non-critical failure, was reported as `healthy`. Both now yield `degraded`,
  which still maps to HTTP 200 — the HTTP contract is unchanged, but the
  payload reports the condition instead of hiding it.
- Controllers derive their HTTP code from `HealthCheckStatus` rather than
  hardcoding `healthy ? 200 : 503`, which would have returned 503 for a
  degraded application.
- The result cache is scoped per group; a filtered run no longer serves results
  computed for a different scope.
- PHPStan level 9 now also analyses `tests/`.

[Unreleased]: https://github.com/kiora/health-check-bundle/compare/v1.0.0...HEAD
