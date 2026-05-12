# Date And Time

Wayfinder uses Carbon for framework date/time handling and exposes a small support facade through `Wayfinder\Support\Date`.

Application code should prefer immutable dates:

```php
use Wayfinder\Support\Date;

$now = Date::now('America/New_York');
$dueAt = Date::parse('2026-05-12 09:00:00', 'UTC')->addDays(7);
```

`Date` returns `Carbon\CarbonImmutable` for parsed and generated values. That gives applications Carbon's comparison, formatting, timezone, and interval helpers without making mutable date objects the default.

## Clocks

Code that needs deterministic time can accept a `Wayfinder\Support\Clock` and pass it into `Date`:

```php
$today = Date::today($clock);
```

Tests can use `Wayfinder\Testing\FrozenClock` to pin the current instant.

## Validation

The `date`, `before`, `before_or_equal`, `after`, and `after_or_equal` validation rules parse dates through `Date`, so timezone-aware strings compare by their real instant:

```php
$request->validate([
    'starts_at' => 'date|after:2026-05-12T12:00:00+00:00',
]);
```

