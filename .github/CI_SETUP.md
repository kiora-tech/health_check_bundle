# CI/CD Setup Documentation

This document describes the complete CI/CD pipeline configured for the Health Check Bundle.

## Overview

The CI/CD pipeline includes:
- ✅ Quality checks (PHPStan, PHP-CS-Fixer, PHPUnit)
- ✅ Integration tests with real services
- ✅ Symfony compatibility matrix testing
- ✅ Security vulnerability scanning
- ✅ Automated dependency updates (Dependabot)
- ✅ Automated releases with changelog generation

## Workflows

### 1. CI Workflow (`ci.yml`)

**Purpose**: Run quality checks on every push and pull request.

**Triggers**:
- Push to `main` and `develop` branches
- Pull requests to `main` and `develop` branches

**Matrix**:
- PHP: 8.3, 8.4
- All combinations tested

**Steps**:
1. Checkout code
2. Setup PHP with extensions (pdo, pdo_mysql, pdo_pgsql, redis, mongodb, zip)
3. Cache Composer dependencies
4. Install dependencies with GrumPHP plugin allowed
5. Run PHPStan (level 8)
6. Run PHP-CS-Fixer (dry-run)
7. Run PHPUnit tests
8. Upload code coverage to Codecov (PHP 8.3 only)

**Expected Duration**: ~2-3 minutes

### 2. Integration Tests Workflow (`integration-tests.yml`)

**Purpose**: Test with real database and service instances.

**Triggers**:
- Push to `main` and `develop` branches
- Pull requests to `main` and `develop` branches
- Manual trigger (`workflow_dispatch`)

**Services**:
- MySQL 8.0 (credentials: test/test)
- PostgreSQL 16 (credentials: test/test)
- Redis 7
- MongoDB 7 (credentials: test/test)
- MinIO (S3-compatible, credentials: minioadmin/minioadmin)

**Steps**:
1. Checkout code
2. Setup Docker Buildx
3. Cache Docker layers
4. Build PHP test image from `docker-compose.test.yml`
5. Start all test services
6. Wait for services to be healthy (with timeouts)
7. Show service status
8. Install dependencies
9. Run integration tests
10. Show logs on failure
11. Clean up containers and volumes

**Expected Duration**: ~5-7 minutes

**Key Features**:
- Uses tmpfs for faster database operations
- Proper health checks for all services
- Automatic cleanup after tests
- Shows detailed logs on failure

### 3. Symfony Compatibility Workflow (`symfony-compatibility.yml`)

**Purpose**: Ensure compatibility with multiple Symfony versions and detect security vulnerabilities.

**Triggers**:
- Push to `main` and `develop` branches
- Pull requests to `main` and `develop` branches
- Weekly schedule (Monday at 9:00 AM UTC)

**Matrix**:
- PHP: 8.3, 8.4
- Symfony: 6.4.*, 7.0.*, 7.1.*, 7.2.*
- Total combinations: 8

**Steps**:
1. Checkout code
2. Setup PHP
3. Install dependencies with specific Symfony version
4. Run tests

**Security Checks Job**:
- Runs Symfony Security Checker
- Scans for known vulnerabilities in dependencies
- Fails if vulnerabilities are found

**Expected Duration**: ~3-5 minutes per matrix combination

### 4. Release Workflow (`release.yml`)

**Purpose**: Automate the release process when a version tag is pushed.

**Triggers**:
- Push of tags matching `v*.*.*` (e.g., v1.0.0, v1.2.3)

**Permissions**:
- `contents: write` (to create releases and upload assets)

**Steps**:
1. Checkout code with full history
2. Setup PHP 8.3
3. Install production dependencies (no dev)
4. Extract version from tag
5. Generate changelog from git commits since last tag
6. Create distribution archives (tar.gz and zip)
   - Excludes: tests, .git, .github, Docker files, build artifacts
7. Create GitHub Release with:
   - Release name and version
   - Generated changelog
   - Installation instructions
   - Distribution archives
8. Trigger Packagist update (requires `PACKAGIST_TOKEN` secret)

**Expected Duration**: ~2-3 minutes

**Required Secrets**:
- `PACKAGIST_TOKEN`: For triggering Packagist package updates

## Dependabot Configuration

**File**: `.github/dependabot.yml`

**Purpose**: Automated dependency updates and security patches.

**Configuration**:

### Composer Dependencies
- **Schedule**: Weekly on Monday at 09:00 UTC
- **Open PRs Limit**: 5
- **Grouping**:
  - `symfony`: All Symfony packages grouped
  - `phpunit`: All PHPUnit packages grouped
  - `development`: Minor and patch dev dependencies grouped
- **Labels**: `dependencies`, `composer`
- **Reviewers/Assignees**: james2001

### GitHub Actions
- **Schedule**: Weekly on Monday at 09:00 UTC
- **Open PRs Limit**: 3
- **Labels**: `dependencies`, `github-actions`
- **Reviewers**: james2001

### Docker
- **Schedule**: Weekly on Monday at 09:00 UTC
- **Open PRs Limit**: 2
- **Labels**: `dependencies`, `docker`
- **Reviewers**: james2001

## Status Badges

The following badges are displayed in the README:

```markdown
[![CI](https://github.com/kiora-tech/health_check_bundle/workflows/CI/badge.svg)](https://github.com/kiora-tech/health_check_bundle/actions/workflows/ci.yml)
[![Integration Tests](https://github.com/kiora-tech/health_check_bundle/workflows/Integration%20Tests/badge.svg)](https://github.com/kiora-tech/health_check_bundle/actions/workflows/integration-tests.yml)
[![Symfony Compatibility](https://github.com/kiora-tech/health_check_bundle/workflows/Symfony%20Compatibility/badge.svg)](https://github.com/kiora-tech/health_check_bundle/actions/workflows/symfony-compatibility.yml)
```

## Secrets Required

### Repository Secrets

1. **PACKAGIST_TOKEN** (Optional, for releases)
   - Required for: `release.yml`
   - Purpose: Trigger Packagist package updates after releases
   - How to get: Generate from Packagist profile settings
   - Settings path: Repository Settings → Secrets and variables → Actions

2. **CODECOV_TOKEN** (Optional, recommended)
   - Required for: `ci.yml` (code coverage upload)
   - Purpose: Upload code coverage reports
   - How to get: Sign up at codecov.io and add repository
   - Settings path: Repository Settings → Secrets and variables → Actions

## Running Workflows Manually

### Integration Tests

```bash
# Via GitHub CLI
gh workflow run integration-tests.yml

# Via GitHub UI
1. Go to Actions tab
2. Select "Integration Tests" workflow
3. Click "Run workflow"
4. Select branch and click "Run workflow"
```

### Release Process

```bash
# Create and push a tag
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0

# The release workflow will automatically:
# 1. Create the release on GitHub
# 2. Generate changelog
# 3. Create distribution archives
# 4. Trigger Packagist update
```

## Local Testing Before Push

### Quality Checks
```bash
# Using Docker (recommended)
make up
make install
make qa

# Or locally
composer qa
```

### Integration Tests
```bash
# Using Docker
make test-integration

# Or manually
docker-compose -f docker-compose.test.yml up --abort-on-container-exit
```

## Troubleshooting

### CI Workflow Fails on PHPStan

**Possible causes**:
- New code doesn't meet PHPStan level 8 standards
- Missing type hints
- Incompatible types

**Solution**:
```bash
make phpstan  # Run locally to see errors
make cs-fix   # Fix coding standards
```

### Integration Tests Timeout

**Possible causes**:
- Services taking too long to start
- Insufficient GitHub Actions resources

**Solution**:
- Check service health checks in `docker-compose.test.yml`
- Increase timeout values in workflow if needed
- Check GitHub Actions status page

### Dependabot PRs Failing

**Possible causes**:
- Breaking changes in dependencies
- Incompatible version constraints

**Solution**:
- Review the PR and test locally
- Update constraints in `composer.json` if needed
- Close the PR and pin the dependency if necessary

## Best Practices

1. **Always run `make qa` before pushing** to catch issues early
2. **Review Dependabot PRs carefully** before merging
3. **Use semantic versioning** for tags (v1.2.3)
4. **Write meaningful commit messages** for better changelogs
5. **Test locally with Docker** to match CI environment
6. **Monitor workflow run times** and optimize if needed

## Future Enhancements

- [ ] Add mutation testing with Infection PHP
- [ ] Add performance benchmarks
- [ ] Add automatic PR labeling
- [ ] Add stale PR/issue management
- [ ] Add code quality metrics (SonarCloud)
- [ ] Add automated security scanning (Snyk)
