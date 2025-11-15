# Security Policy

## Supported Versions

We actively support the following versions with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability, please report it by emailing:

📧 **security@kiora.tech**

### What to Include

Please include as much information as possible:

- **Type of vulnerability** (e.g., SQL injection, XSS, authentication bypass)
- **Full path** of source file(s) related to the vulnerability
- **Location** of the affected code (tag/branch/commit or direct URL)
- **Step-by-step instructions** to reproduce the issue
- **Proof-of-concept or exploit code** (if possible)
- **Impact** of the vulnerability (what an attacker could do)

### Response Timeline

- **Initial response**: Within 48 hours
- **Vulnerability assessment**: Within 5 business days
- **Fix timeline**: Depends on severity
  - Critical: 1-7 days
  - High: 7-14 days
  - Medium: 14-30 days
  - Low: 30-90 days

### Security Update Process

1. We will confirm the vulnerability and determine its severity
2. We will develop and test a fix
3. We will release a security patch
4. We will publicly disclose the vulnerability after the fix is released

### Coordinated Disclosure

We follow responsible disclosure practices:

- We will work with you to understand and validate the vulnerability
- We will credit you in the security advisory (if you wish)
- We request that you do not publicly disclose the vulnerability until we release a fix

## Security Best Practices for Users

### 1. Protect Health Check Endpoints

While health check endpoints are designed to be safe, we recommend:

```yaml
# config/packages/security.yaml
security:
    access_control:
        # Restrict /health to trusted networks only
        - { path: ^/health, roles: IS_AUTHENTICATED_ANONYMOUSLY, ips: [127.0.0.1, ::1, 10.0.0.0/8] }

        # Or use /ping for public access (no sensitive info)
        - { path: ^/ping, roles: PUBLIC_ACCESS }
```

### 2. Rate Limiting

Implement rate limiting to prevent abuse:

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        health_check:
            policy: 'fixed_window'
            limit: 60
            interval: '1 minute'
```

### 3. Monitoring

Monitor health check endpoints for:
- Unusual access patterns
- Excessive failed checks
- Suspicious user agents

### 4. Sensitive Information

This bundle is designed to **never expose**:
- Database credentials
- Service versions
- Internal paths
- Stack traces in production
- Configuration details

If you notice any information leakage, please report it as a security issue.

### 5. Keep Dependencies Updated

Regularly update the bundle and its dependencies:

```bash
composer update kiora/health-check-bundle
```

Subscribe to GitHub releases to stay informed about security updates.

## Security Features

This bundle includes security features by default:

- ✅ Generic error messages (no sensitive details)
- ✅ Security headers (X-Robots-Tag, X-Content-Type-Options, Cache-Control)
- ✅ No version information exposed
- ✅ No database schema information
- ✅ Configurable timeouts to prevent resource exhaustion
- ✅ No stack traces in production

## Known Security Considerations

### Health Check Endpoint as Information Disclosure

Health check endpoints can reveal:
- Which services your application depends on (database, Redis, S3, etc.)
- Whether those services are operational

**Mitigation**: Use IP restrictions or authentication for production environments.

### Denial of Service via Health Checks

Frequent health check requests can overload your services.

**Mitigation**:
- Use the `/ping` endpoint for liveness probes
- Use the `/health` endpoint for readiness probes (less frequently)
- Implement rate limiting

## Hall of Fame

We appreciate security researchers who responsibly disclose vulnerabilities:

*(No vulnerabilities reported yet)*

## Questions?

For security-related questions that are not vulnerabilities, please open a GitHub Discussion.

For actual vulnerabilities, always use **security@kiora.tech**.
