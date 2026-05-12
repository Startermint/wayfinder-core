# HTTP

Wayfinder keeps its own small HTTP API for controllers and middleware, backed internally by Symfony HttpFoundation.

Existing controller code should continue to typehint and return Wayfinder objects:

```php
use Wayfinder\Http\Request;
use Wayfinder\Http\Response;

final class ContactController
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        return Response::redirect('/thanks');
    }
}
```

## Symfony Access

Use the Symfony accessors only when a lower-level integration needs them:

```php
$symfonyRequest = $request->toSymfonyRequest();
$symfonyResponse = $response->toSymfonyResponse();
```

The shorter aliases are also available:

```php
$request->symfony();
$response->symfony();
```

## Compatibility

Wayfinder request helpers remain the public convention:

- `input()`
- `query()`
- `request()`
- `cookies()`
- `files()`
- `headers()`
- `body()`
- `validate()`

JSON request bodies are parsed into `request()` data when the content type is JSON and no form payload is present. Uploaded files are normalized to Wayfinder's existing metadata array shape so validation file rules keep working.

Responses are emitted through Symfony HttpFoundation, while Wayfinder response factories remain unchanged:

- `Response::text()`
- `Response::html()`
- `Response::json()`
- `Response::redirect()`
- `Response::stream()`
