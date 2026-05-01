# Wayfinder Core

Wayfinder is the reusable core framework package behind Stackmint.

It provides the runtime pieces that Stackmint builds on: routing, HTTP, minimal DI, configuration, views, sessions, database access, console tooling, modules, and testing support.

## Philosophy

Wayfinder is designed around:

- explicit wiring
- low magic
- model-first application code
- AI-readable structure
- a small dependency surface

It intentionally does not include:

- an ORM
- a queue system by default
- a large hidden abstraction layer

If an application needs queues, Wayfinder now includes an optional Laravel-backed queue integration layer that can be bound explicitly without changing the default framework path.

## Mental Model

- `Model` = entity behavior
- `Query` = complex read shape
- `Service` = workflow
- `DB` = low-level control
- `DTO` = explicit output shape

## View Helpers

In PHP views, prefer Wayfinder helpers over raw escaping or hard-coded internal paths:

```php
<?= e($title) ?>
<?= e(url('health')) ?>
<?= e(asset('img/photo.jpg')) ?>
```

See `docs/view-helpers.md` for the full helper list.

## Path Helpers

Use path helpers after bootstrap instead of manual `__DIR__` path assembly:

```php
storage_path('logs/app.log')
database_path('migrations')
config_path('app.php')
```

See `docs/path-helpers.md`.

## Use Wayfinder Through Stackmint

Most developers should start from the Stackmint starter rather than consuming the core package in isolation.

- Stackmint starter: <https://github.com/trafficinc/stackmint>
- Full docs: <https://github.com/trafficinc/stackmint-docs>

## Package

- Composer package: `wayfinder/core`
- Namespace root: `Wayfinder\\`
