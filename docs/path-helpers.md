# Path Helpers

Wayfinder path helpers resolve application filesystem paths after bootstrap:

```php
base_path('composer.json');
app_path('Controllers/HomeController.php');
config_path('app.php');
database_path('migrations');
lang_path('en/messages.php');
public_path('index.php');
resource_path('views/home.php');
storage_path('logs/app.log');
```

Use them instead of repeating `__DIR__ . '/../...'` in application and framework-facing code.
