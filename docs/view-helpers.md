# View Helpers

Use Wayfinder helpers in PHP views instead of raw escaping or hard-coded internal paths.

Developer-owned application views live under `resources/views` by default. Dot
notation resolves against that directory, so `home.index` maps to
`resources/views/home/index.php`.

```php
<?= e($title) ?>
<a href="<?= e(url('health')) ?>">Health</a>
<img src="<?= e(asset('img/photo.jpg')) ?>" alt="">
```

Helpers:

- `e($value)` escapes normal text and attribute output.
- `url('path')` generates a fully qualified application URL.
- `url('path', [1])` appends positional path parameters.
- `url()->current()` returns the current URL without query string.
- `url()->full()` returns the current URL with query string.
- `url()->previous()` returns the referer URL or app root fallback.
- `secure_url('path')` generates an HTTPS URL.
- `asset('path')` and `assets('path')` generate public asset URLs.

Only use `Wayfinder\View\HtmlString` for trusted framework-generated HTML. Do not use it for unsanitized user-authored HTML.
