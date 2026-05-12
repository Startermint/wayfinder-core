# Security Hardening

Wayfinder provides explicit security primitives that applications should wire during bootstrap.

Recommended production defaults:

- Configure `app.trusted_hosts` from `TRUSTED_HOSTS` or `APP_URL`.
- Configure `app.trusted_proxies` only for load balancers and reverse proxies you control.
- Add `host` and `secure_headers` to global middleware.
- Use `SESSION_SECURE=true` behind HTTPS.
- Keep `SESSION_SAME_SITE=Lax` unless a cross-site integration requires otherwise.
- If a cookie uses `SameSite=None`, it must also use `Secure`.
- Keep `APP_DEBUG=false` in production.

The default security headers middleware adds:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`

Applications with stricter frontend requirements can replace or extend these headers in their own middleware.
