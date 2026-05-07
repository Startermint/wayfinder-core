# Validation

Wayfinder validates request input through `Request::validate()`.

```php
$data = $request->validate([
    'name' => 'required|string|max:100',
    'email' => 'required|email|unique:users,email',
    'items' => 'array|max:10',
    'items.*.sku' => 'required|string|alpha_dash',
    'items.*.qty' => 'required|integer|min:1',
]);
```

Supported rule groups:

- Presence: `required`, `nullable`, `sometimes`, `present`, `filled`, `required_if`, `required_unless`, `required_with`, `required_without`
- Types: `string`, `integer`, `numeric`, `boolean`, `array`
- Strings and formats: `email`, `url`, `date`, `timezone`, `ip`, `ipv4`, `ipv6`, `json`, `lowercase`, `uppercase`, `alpha`, `alpha_num`, `alpha_dash`, `slug`, `uuid`, `regex`, `not_regex`, `in`, `not_in`
- Comparisons: `min`, `max`, `size`, `between`, `gt`, `gte`, `lt`, `lte`
- Dates: `before`, `before_or_equal`, `after`, `after_or_equal`
- Confirmation: `confirmed`, `same`
- Database: `exists:table,column`, `unique:table,column,ignore,id_column`
- Files: `file`, `uploaded`, `image`, `mimes`, `max_file`

Nested input uses dot notation and `*` wildcards:

```php
$request->validate([
    'contacts.*.email' => 'required|email',
]);
```

For regex rules that contain pipe characters, use array rule syntax:

```php
$request->validate([
    'code' => ['required', 'regex:/^(A|B)-[0-9]+$/'],
]);
```
