# Security Policy

## Supported Versions

Use this section to tell people about which versions of your project are currently being supported with security updates.

| Version | Laravel Version | PHP Version | Security Fixes |
|---------|----------------|-------------|----------------|
| 1.x     | 10.x, 11.x    | ≥8.2        | ✅            |
| 0.x     | 10.x          | ≥8.2        | ❌            |

## Reporting a Vulnerability

We take the security of `laravel-salesforce` seriously. If you believe you have found a security vulnerability, please report it to us as described below.

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to:
- Primary: [your-security-email@example.com](mailto:gkoutzamanis.a@gmail.com)
- Secondary: Create a [GitHub Security Advisory](https://github.com/antogkou/laravel-salesforce/security/advisories/new)

You should receive a response within 48 hours. If for some reason you do not, please follow up via email to ensure we received your original message.

Please include the following information in your report:

- Type of issue (e.g. buffer overflow, SQL injection, cross-site scripting, etc.)
- Full paths of source file(s) related to the manifestation of the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

## Preferred Languages

We prefer all communications to be in English.

## Security Update Process

1. The security report is received and assigned to an owner
2. The problem is confirmed and a list of all affected versions is determined
3. Code is audited to find any potential similar problems
4. Fixes are prepared for all supported releases
5. New versions are released for all supported versions

## Security Related Configuration

This package handles sensitive Salesforce credentials. Please ensure:

1. Your `.env` file is not committed to version control
2. The environment variables are properly secured:

## Best Practices

When using this package:

1. Always use environment variables for sensitive configuration
2. Regularly update to the latest version
3. Set appropriate access controls for your Salesforce integration
4. Monitor your application logs for suspicious activity
5. Follow Laravel security best practices

## Security Advisories

Security advisories will be published through:

1. GitHub Security Advisories
2. Release Notes
3. Direct email to registered security contacts (if provided)

## Comments on this Policy

If you have suggestions on how this process could be improved, please submit a pull request or create an issue to discuss.
