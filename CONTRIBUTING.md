# Contributing

Thanks for taking the time to improve Wayfinder Core.

## Development

```bash
composer install
./vendor/bin/phpunit
```

Wayfinder favors explicit code, small abstractions, and behavior that is easy to trace from application bootstrap through request handling.

## Pull Requests

- Keep changes scoped to one concern.
- Add or update tests for behavior changes.
- Update documentation when public APIs, configuration, commands, or security-sensitive behavior changes.
- Avoid committing generated files such as `vendor/`, Composer caches, PHPUnit caches, or local runtime artifacts.

## Security

Please report security issues privately using the process in `SECURITY.md`.
