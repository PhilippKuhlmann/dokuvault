[Deutsch](SECURITY.de.md) · **English**

# Security policy

## Supported versions

The project uses date-based versioning (`YY.MM.DD`, see [changelog.md](changelog.md)). Security
fixes go into the current version — please keep your installation up to date.

## Reporting a vulnerability

Please do **not** report security issues through public GitHub issues.

Use **[Report a vulnerability](https://github.com/PhilippKuhlmann/dokuvault/security/advisories/new)**
in GitHub instead — that creates a private report only you and the maintainer can see.
Alternatively, send an email to the maintainer (see the GitHub profile of the repository owner).

A description of the issue and, if possible, steps to reproduce it are helpful. You will get a reply
as soon as possible; fixed issues are noted in the changelog.

Reports are welcome in **English or German**.

## Notes on the security model

- **Credentials at rest**: passwords stored in the application (devices, logins and so on) are
  encrypted with the Laravel `APP_KEY` (`Crypt`). The `APP_KEY` is therefore the master key — keep it
  safe and include it in your backup strategy. If it is lost, every stored credential becomes
  unreadable.
- **Sessions** are stored encrypted (`SESSION_ENCRYPT=true`); cookies are `HttpOnly` and, in
  production, `Secure` (HTTPS only).
- **Operation**: the application is meant to run behind HTTPS. In production
  (`APP_ENV=production`) URLs are forced to HTTPS automatically.
- **Demo accounts**: the demo accounts created by the seeder (`admin`/`password` and so on) are meant
  for local test environments only and must be removed in production.
- **Trusted proxies**: if a reverse proxy sits in front of the app, enter it in `.env` under
  `TRUSTED_PROXIES`. Without it the app sees the proxy's address instead of the visitor's — which
  also lands in the audit log. Do not use `*` unless the proxy really is the only way in; otherwise
  anyone reaching the app directly can claim any origin via `X-Forwarded-For`.
