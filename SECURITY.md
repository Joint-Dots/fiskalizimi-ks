# Security Policy

## Supported Versions

Security fixes are provided for the latest tagged minor release. Pre-1.0
releases may contain breaking changes in the next minor version.

## Reporting a Vulnerability

Use GitHub's private vulnerability reporting or security advisory feature for
the repository. Do not open a public issue containing:

- Private keys or certificates
- API tokens
- Real taxpayer or customer data
- Signed coupon payloads or QR contents
- Production URLs, logs, or database exports

Include the affected version, impact, reproduction steps using synthetic data,
and a proposed mitigation when available.

## Operational Security

This package handles fiscal signing material. Deployments must:

- Keep each private key outside source control and the web root
- Restrict key and journal access to the application identity
- Use HTTPS with certificate verification
- Disable debug mode in production
- Keep fiscal request logging disabled unless needed for controlled diagnosis
- Protect and monitor the queue worker and fiscal database
- Test backup restoration without copying production keys into development

