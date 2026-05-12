# Security Hardening

Wayfinder provides explicit security primitives that applications should wire during bootstrap.

Recommended production defaults:

- Configure `app.trusted_hosts` from `TRUSTED_HOSTS` or `APP_URL`.
- Configure `app.trusted_proxies` only for load balancers and reverse proxies you control.
- Add `host`, `request_id`, and `secure_headers` to global middleware.
- Use `SESSION_SECURE=true` behind HTTPS.
- Keep `SESSION_SAME_SITE=Lax` unless a cross-site integration requires otherwise.
- If a cookie uses `SameSite=None`, it must also use `Secure`.
- Keep `APP_DEBUG=false` in production.
- Enable `SECURITY_HSTS=true` only when the app is served exclusively over HTTPS.
- Set `SECURITY_CSP` after verifying the app's frontend asset requirements.

The default security headers middleware adds:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`

Applications with stricter frontend requirements can replace or extend these headers in their own middleware.

The `RequestId` middleware returns an `X-Request-Id` response header. It preserves a valid inbound request ID from a proxy or load balancer and generates a safe ID when one is missing.
