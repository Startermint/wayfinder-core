<?php

declare(strict_types=1);

namespace Wayfinder\Validation;

use Wayfinder\Database\DB;
use Wayfinder\Http\Request;
use Wayfinder\Http\ValidationException;

final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     */
    public function __construct(
        private readonly Request $request,
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly string $bag = 'default',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        foreach ($this->rules as $field => $fieldRules) {
            $ruleset = $this->normalizeRules($fieldRules);
            $targets = str_contains($field, '*')
                ? $this->expandWildcardTargets($field)
                : [[$field, $field, $this->value($field), $this->has($field)]];

            foreach ($targets as [$errorField, $dataField, $value, $isPresent]) {
                $this->validateField($field, $errorField, $dataField, $value, $isPresent, $ruleset);
            }
        }

        if ($this->errors !== []) {
            throw new ValidationException($this->errors, 'The given data was invalid.', $this->request, $this->bag);
        }

        return $this->validated;
    }

    /**
     * @param list<string> $ruleset
     */
    private function validateField(string $ruleField, string $errorField, string $dataField, mixed $value, bool $isPresent, array $ruleset): void
    {
        $ruleNames = array_map(fn (string $rule): string => $this->parseRule($rule)[0], $ruleset);
        $isEmpty = $this->isEmpty($value);

        if (! $isPresent && in_array('sometimes', $ruleNames, true)) {
            return;
        }

        if (! $isPresent && in_array('filled', $ruleNames, true)) {
            return;
        }

        if (in_array('present', $ruleNames, true) && ! $isPresent) {
            $this->addError($errorField, 'present', 'This field must be present.');
            return;
        }

        if ($isPresent && in_array('filled', $ruleNames, true) && $isEmpty) {
            $this->addError($errorField, 'filled', 'This field must not be empty.');
            return;
        }

        if (in_array('required', $ruleNames, true) && $isEmpty) {
            $this->addError($errorField, 'required', 'This field is required.');
            return;
        }

        foreach ($ruleset as $rule) {
            [$ruleName, $parameters] = $this->parseRule($rule);

            if ($this->requiresValue($ruleName, $parameters) && $isEmpty) {
                $this->addError($errorField, $ruleName, 'This field is required.');
                return;
            }
        }

        if ($isEmpty) {
            if (in_array('nullable', $ruleNames, true)) {
                $this->setValidated($dataField, null);
            }

            return;
        }

        foreach ($ruleset as $rule) {
            [$ruleName, $parameters] = $this->parseRule($rule);

            if (in_array($ruleName, ['required', 'nullable', 'sometimes', 'present'], true)) {
                continue;
            }

            $this->validateRule($ruleField, $errorField, $dataField, $value, $ruleName, $parameters, $ruleNames);
        }

        if (! isset($this->errors[$errorField]) && $isPresent) {
            $this->setValidated($dataField, $value);
        }
    }

    /**
     * @param list<string> $parameters
     * @param list<string> $ruleNames
     */
    private function validateRule(string $ruleField, string $errorField, string $dataField, mixed $value, string $ruleName, array $parameters, array $ruleNames): void
    {
        if ($ruleName === 'filled' && $this->isEmpty($value)) {
            $this->addError($errorField, 'filled', 'This field must not be empty.');
        }

        if ($ruleName === 'string' && ! is_string($value)) {
            $this->addError($errorField, 'string', 'This field must be a string.');
        }

        if ($ruleName === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($errorField, 'integer', 'This field must be an integer.');
        }

        if ($ruleName === 'numeric' && ! is_numeric($value)) {
            $this->addError($errorField, 'numeric', 'This field must be a number.');
        }

        if ($ruleName === 'boolean' && filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null) {
            $this->addError($errorField, 'boolean', 'This field must be a boolean.');
        }

        if ($ruleName === 'array' && ! is_array($value)) {
            $this->addError($errorField, 'array', 'This field must be an array.');
        }

        if ($ruleName === 'file' && ! $this->isUploadedFile($value)) {
            $this->addError($errorField, 'file', 'This field must be a file.');
        }

        if ($ruleName === 'uploaded' && (! $this->isUploadedFile($value) || (int) ($value['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
            $this->addError($errorField, 'uploaded', 'This file failed to upload.');
        }

        if ($ruleName === 'image' && (! $this->isUploadedFile($value) || ! $this->isImageFile($value))) {
            $this->addError($errorField, 'image', 'This field must be an image.');
        }

        if ($ruleName === 'mimes' && (! $this->isUploadedFile($value) || ! $this->hasAllowedMime($value, $parameters))) {
            $this->addError($errorField, 'mimes', 'This file type is invalid.');
        }

        if ($ruleName === 'max_file' && (! $this->isUploadedFile($value) || (int) ($value['size'] ?? 0) > ((int) ($parameters[0] ?? 0) * 1024))) {
            $this->addError($errorField, 'max_file', "This file must not exceed {$parameters[0]} kilobytes.");
        }

        if ($ruleName === 'email' && (! is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false)) {
            $this->addError($errorField, 'email', 'This field must be a valid email address.');
        }

        if ($ruleName === 'url' && (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false)) {
            $this->addError($errorField, 'url', 'This field must be a valid URL.');
        }

        if ($ruleName === 'date' && ! $this->isDate($value)) {
            $this->addError($errorField, 'date', 'This field must be a valid date.');
        }

        if ($ruleName === 'timezone' && (! is_string($value) || ! in_array($value, timezone_identifiers_list(), true))) {
            $this->addError($errorField, 'timezone', 'This field must be a valid timezone.');
        }

        if ($ruleName === 'ip' && filter_var($value, FILTER_VALIDATE_IP) === false) {
            $this->addError($errorField, 'ip', 'This field must be a valid IP address.');
        }

        if ($ruleName === 'ipv4' && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $this->addError($errorField, 'ipv4', 'This field must be a valid IPv4 address.');
        }

        if ($ruleName === 'ipv6' && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            $this->addError($errorField, 'ipv6', 'This field must be a valid IPv6 address.');
        }

        if ($ruleName === 'json' && (! is_string($value) || ! $this->isJson($value))) {
            $this->addError($errorField, 'json', 'This field must be valid JSON.');
        }

        if ($ruleName === 'lowercase' && (! is_string($value) || mb_strtolower($value) !== $value)) {
            $this->addError($errorField, 'lowercase', 'This field must be lowercase.');
        }

        if ($ruleName === 'uppercase' && (! is_string($value) || mb_strtoupper($value) !== $value)) {
            $this->addError($errorField, 'uppercase', 'This field must be uppercase.');
        }

        if ($ruleName === 'alpha' && (! is_string($value) || preg_match('/^\pL+$/u', $value) !== 1)) {
            $this->addError($errorField, 'alpha', 'This field may only contain letters.');
        }

        if ($ruleName === 'alpha_num' && (! is_string($value) || preg_match('/^[\pL\pN]+$/u', $value) !== 1)) {
            $this->addError($errorField, 'alpha_num', 'This field may only contain letters and numbers.');
        }

        if ($ruleName === 'alpha_dash' && (! is_string($value) || preg_match('/^[\pL\pN_-]+$/u', $value) !== 1)) {
            $this->addError($errorField, 'alpha_dash', 'This field may only contain letters, numbers, dashes, and underscores.');
        }

        if ($ruleName === 'slug' && (! is_string($value) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1)) {
            $this->addError($errorField, 'slug', 'This field must be a valid slug.');
        }

        if ($ruleName === 'uuid' && (! is_string($value) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1)) {
            $this->addError($errorField, 'uuid', 'This field must be a valid UUID.');
        }

        if ($ruleName === 'regex' && (! isset($parameters[0]) || ! is_scalar($value) || @preg_match($parameters[0], (string) $value) !== 1)) {
            $this->addError($errorField, 'regex', 'This field format is invalid.');
        }

        if ($ruleName === 'not_regex' && isset($parameters[0]) && is_scalar($value) && @preg_match($parameters[0], (string) $value) === 1) {
            $this->addError($errorField, 'not_regex', 'This field format is invalid.');
        }

        if ($ruleName === 'in' && ! in_array((string) $value, $parameters, true)) {
            $this->addError($errorField, 'in', 'The selected value is invalid.');
        }

        if ($ruleName === 'not_in' && in_array((string) $value, $parameters, true)) {
            $this->addError($errorField, 'not_in', 'The selected value is invalid.');
        }

        if (in_array($ruleName, ['min', 'max', 'size', 'between', 'gt', 'gte', 'lt', 'lte'], true)) {
            $this->validateComparableRule($errorField, $value, $ruleName, $parameters, in_array('string', $ruleNames, true));
        }

        if (in_array($ruleName, ['before', 'before_or_equal', 'after', 'after_or_equal'], true)) {
            $this->validateDateComparison($errorField, $value, $ruleName, $parameters);
        }

        if ($ruleName === 'confirmed') {
            $confirmKey = "{$dataField}_confirmation";
            if ($value !== $this->value($confirmKey)) {
                $this->addError($errorField, 'confirmed', 'This field confirmation does not match.');
            }
        }

        if ($ruleName === 'same') {
            $otherField = $parameters[0] ?? '';
            if ($value !== $this->value($otherField)) {
                $this->addError($errorField, 'same', "This field must match {$otherField}.");
            }
        }

        if ($ruleName === 'exists' && ! $this->passesExistsRule($ruleField, $value, $parameters)) {
            $this->addError($errorField, 'exists', 'The selected value is invalid.');
        }

        if ($ruleName === 'unique' && ! $this->passesUniqueRule($ruleField, $value, $parameters)) {
            $this->addError($errorField, 'unique', 'This value has already been taken.');
        }
    }

    /**
     * @param list<string> $parameters
     */
    private function validateComparableRule(string $field, mixed $value, string $ruleName, array $parameters, bool $isStringRule): void
    {
        $first = isset($parameters[0]) ? (float) $this->comparisonValue($parameters[0]) : 0.0;
        $actual = $this->measure($value, $isStringRule);
        $failed = match ($ruleName) {
            'min' => $actual < $first,
            'max' => $actual > $first,
            'size' => $actual !== $first,
            'between' => $actual < $first || $actual > (float) $this->comparisonValue($parameters[1] ?? 0),
            'gt' => $actual <= $first,
            'gte' => $actual < $first,
            'lt' => $actual >= $first,
            'lte' => $actual > $first,
            default => false,
        };

        if ($failed) {
            $this->addError($field, $ruleName, $this->comparisonMessage($value, $ruleName, $parameters, $isStringRule));
        }
    }

    /**
     * @param list<string> $parameters
     */
    private function validateDateComparison(string $field, mixed $value, string $ruleName, array $parameters): void
    {
        $actual = $this->timestamp($value);
        $compare = $this->timestamp($this->comparisonValue($parameters[0] ?? ''));

        if ($actual === null || $compare === null) {
            $this->addError($field, $ruleName, 'This field must be a valid date.');
            return;
        }

        $failed = match ($ruleName) {
            'before' => $actual >= $compare,
            'before_or_equal' => $actual > $compare,
            'after' => $actual <= $compare,
            'after_or_equal' => $actual < $compare,
            default => false,
        };

        if ($failed) {
            $this->addError($field, $ruleName, "This field must be {$ruleName} {$parameters[0]}.");
        }
    }

    private function requiresValue(string $ruleName, array $parameters): bool
    {
        return match ($ruleName) {
            'required_if' => $this->value((string) ($parameters[0] ?? '')) === ($parameters[1] ?? null),
            'required_unless' => $this->value((string) ($parameters[0] ?? '')) !== ($parameters[1] ?? null),
            'required_with' => $this->hasAny($parameters),
            'required_without' => ! $this->hasAny($parameters),
            default => false,
        };
    }

    /**
     * @param list<string> $parameters
     */
    private function hasAny(array $parameters): bool
    {
        foreach ($parameters as $field) {
            if ($this->has($field) && ! $this->isEmpty($this->value($field))) {
                return true;
            }
        }

        return false;
    }

    private function comparisonValue(string $parameter): mixed
    {
        if ($this->has($parameter)) {
            return $this->value($parameter);
        }

        return $this->resolveValidationParameter($parameter);
    }

    private function measure(mixed $value, bool $isStringRule): float
    {
        if (is_array($value)) {
            return (float) count($value);
        }

        if (! $isStringRule && is_numeric($value)) {
            return (float) $value;
        }

        return (float) mb_strlen((string) $value);
    }

    private function comparisonMessage(mixed $value, string $ruleName, array $parameters, bool $isStringRule): string
    {
        $target = $parameters[0] ?? '';

        if ($ruleName === 'between') {
            return "This field must be between {$parameters[0]} and {$parameters[1]}.";
        }

        if ($ruleName === 'size') {
            return "This field must be {$target}.";
        }

        if ($ruleName === 'min') {
            return match (true) {
                is_array($value) => "This field must have at least {$target} items.",
                $isStringRule || ! is_numeric($value) => "This field must be at least {$target} characters.",
                default => "This field must be at least {$target}.",
            };
        }

        if ($ruleName === 'max') {
            return match (true) {
                is_array($value) => "This field must not have more than {$target} items.",
                $isStringRule || ! is_numeric($value) => "This field must not exceed {$target} characters.",
                default => "This field must not be greater than {$target}.",
            };
        }

        return "This field must be {$ruleName} {$target}.";
    }

    /**
     * @return list<array{0: string, 1: string, 2: mixed, 3: bool}>
     */
    private function expandWildcardTargets(string $field): array
    {
        $matches = [];
        $this->expandWildcardRecursive(explode('.', $field), $this->data, [], $matches);

        return $matches;
    }

    /**
     * @param list<string> $segments
     * @param list<string> $path
     * @param list<array{0: string, 1: string, 2: mixed, 3: bool}> $matches
     */
    private function expandWildcardRecursive(array $segments, mixed $current, array $path, array &$matches): void
    {
        if ($segments === []) {
            $field = implode('.', $path);
            $matches[] = [$field, $field, $current, true];
            return;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (! is_array($current)) {
                return;
            }

            foreach ($current as $key => $value) {
                $this->expandWildcardRecursive($segments, $value, [...$path, (string) $key], $matches);
            }

            return;
        }

        if (is_array($current) && array_key_exists($segment, $current)) {
            $this->expandWildcardRecursive($segments, $current[$segment], [...$path, $segment], $matches);
            return;
        }

        $matches[] = [implode('.', [...$path, $segment, ...$segments]), implode('.', [...$path, $segment, ...$segments]), null, false];
    }

    private function value(string $key): mixed
    {
        $current = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    private function has(string $key): bool
    {
        $current = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }

    private function setValidated(string $key, mixed $value): void
    {
        if (! str_contains($key, '.')) {
            $this->validated[$key] = $value;
            return;
        }

        $current = &$this->validated;
        foreach (explode('.', $key) as $segment) {
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * @param string|list<string> $rules
     * @return list<string>
     */
    private function normalizeRules(string|array $rules): array
    {
        return is_array($rules) ? array_values($rules) : explode('|', $rules);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function parseRule(string $rule): array
    {
        $segments = explode(':', $rule, 2);
        $name = $segments[0];
        $parameters = isset($segments[1]) ? array_map('trim', explode(',', $segments[1])) : [];

        return [$name, $parameters];
    }

    private function addError(string $field, string $rule, string $default): void
    {
        $this->errors[$field][] = $this->messages["{$field}.{$rule}"] ?? $this->messages[$this->wildcardMessageKey($field, $rule)] ?? $default;
    }

    private function wildcardMessageKey(string $field, string $rule): string
    {
        return preg_replace('/\.\d+\./', '.*.', "{$field}.{$rule}") ?? "{$field}.{$rule}";
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && count($value) === 0);
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && strtotime($value) !== false;
    }

    private function timestamp(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }

    private function isJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function isUploadedFile(mixed $value): bool
    {
        return is_array($value)
            && array_key_exists('tmp_name', $value)
            && array_key_exists('name', $value)
            && array_key_exists('size', $value)
            && array_key_exists('error', $value);
    }

    /**
     * @param array<string, mixed> $file
     */
    private function isImageFile(array $file): bool
    {
        $mime = is_string($file['type'] ?? null) ? $file['type'] : '';

        return str_starts_with($mime, 'image/');
    }

    /**
     * @param array<string, mixed> $file
     * @param list<string> $extensions
     */
    private function hasAllowedMime(array $file, array $extensions): bool
    {
        $name = is_string($file['name'] ?? null) ? strtolower($file['name']) : '';
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return in_array($extension, array_map('strtolower', $extensions), true);
    }

    /**
     * @param list<string> $parameters
     */
    private function passesExistsRule(string $field, mixed $value, array $parameters): bool
    {
        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? $field;

        if (! is_string($table) || $table === '') {
            throw new \InvalidArgumentException(sprintf('Validation rule [exists] for [%s] requires a table name.', $field));
        }

        return DB::table($table)->where($column, $value)->exists();
    }

    /**
     * @param list<string> $parameters
     */
    private function passesUniqueRule(string $field, mixed $value, array $parameters): bool
    {
        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? $field;
        $ignore = $this->resolveValidationParameter($parameters[2] ?? null);
        $idColumn = $parameters[3] ?? 'id';

        if (! is_string($table) || $table === '') {
            throw new \InvalidArgumentException(sprintf('Validation rule [unique] for [%s] requires a table name.', $field));
        }

        $query = DB::table($table)->where($column, $value);

        if ($ignore !== null && $ignore !== '') {
            $query->where((string) $idColumn, '!=', $ignore);
        }

        return ! $query->exists();
    }

    private function resolveValidationParameter(mixed $parameter): mixed
    {
        if (! is_string($parameter)) {
            return $parameter;
        }

        if (preg_match('/^\{\$([A-Za-z_][A-Za-z0-9_]*)\}$/', $parameter, $matches) === 1) {
            $key = $matches[1];

            return $this->request->route($key, $this->request->input($key));
        }

        return $parameter;
    }
}
