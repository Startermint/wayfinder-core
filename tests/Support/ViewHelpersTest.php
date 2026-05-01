<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Support;

use PHPUnit\Framework\TestCase;

final class ViewHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        \Wayfinder\Database\DB::setResolver(static fn (?string $name = null) => throw new \RuntimeException('DB resolver not configured.'));
        \Wayfinder\Support\PathResolver::setResolver(static fn () => throw new \RuntimeException('Path resolver not configured.'));
        \Wayfinder\Routing\UrlGenerator::setResolver(static fn () => throw new \RuntimeException('URL generator resolver not configured.'));
    }

    public function test_e_escapes_html_special_characters(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
            \e('<script>alert("x")</script>'),
        );
    }

    public function test_e_does_not_double_encode_existing_entities(): void
    {
        $root = dirname(__DIR__, 2);
        $code = sprintf(
            'require %s; require %s; echo e("&lt;strong&gt;Safe&lt;/strong&gt;");',
            var_export($root . '/src/Support/helpers.php', true),
            var_export($root . '/vendor/autoload.php', true),
        );

        exec(PHP_BINARY . ' -r ' . escapeshellarg($code), $output, $exitCode);

        self::assertSame(0, $exitCode);
        self::assertSame('&lt;strong&gt;Safe&lt;/strong&gt;', implode('', $output));
    }

    public function test_e_allows_explicit_htmlable_values(): void
    {
        self::assertSame(
            '<strong>Trusted</strong>',
            \e(new \Wayfinder\View\HtmlString('<strong>Trusted</strong>')),
        );
    }

    public function test_e_returns_empty_string_for_null(): void
    {
        self::assertSame('', \e(null));
    }

    public function test_attrs_renders_scalar_and_boolean_attributes(): void
    {
        self::assertSame(
            'href="/catalog" disabled data-id="42"',
            \attrs([
                'href' => '/catalog',
                'disabled' => true,
                'hidden' => false,
                'data-id' => 42,
            ]),
        );
    }

    public function test_attrs_joins_array_values_and_escapes_output(): void
    {
        self::assertSame(
            'class="btn btn-primary" title="A &quot;quote&quot;"',
            \attrs([
                'class' => ['btn', null, 'btn-primary', false],
                'title' => 'A "quote"',
            ]),
        );
    }

    public function test_attrs_skips_invalid_or_unsupported_values(): void
    {
        self::assertSame(
            'aria-label="Open"',
            \attrs([
                'bad key!' => 'ignored',
                'config' => new \stdClass(),
                'aria-label' => 'Open',
            ]),
        );
    }

    public function test_checked_renders_attribute_when_values_match(): void
    {
        self::assertSame('checked', \checked('card', 'card'));
        self::assertSame('', \checked('card', 'bank'));
    }

    public function test_checked_supports_array_values(): void
    {
        self::assertSame('checked', \checked(['red', 'blue'], 'blue'));
        self::assertSame('', \checked(['red', 'blue'], 'green'));
    }

    public function test_selected_renders_attribute_when_values_match(): void
    {
        self::assertSame('selected', \selected(2, '2'));
        self::assertSame('', \selected(2, '2', true));
    }

    public function test_disabled_renders_attribute_when_true(): void
    {
        self::assertSame('disabled', \disabled());
        self::assertSame('', \disabled(false));
    }

    public function test_db_helper_returns_database_connection(): void
    {
        $database = new \Wayfinder\Database\Database(['driver' => 'sqlite', 'path' => ':memory:']);
        \Wayfinder\Database\DB::setResolver(static fn (?string $name = null): \Wayfinder\Database\Database => $database);

        self::assertSame($database, \db());
    }

    public function test_db_helper_can_resolve_named_connection(): void
    {
        $default = new \Wayfinder\Database\Database(['driver' => 'sqlite', 'path' => ':memory:']);
        $analytics = new \Wayfinder\Database\Database(['driver' => 'sqlite', 'path' => ':memory:']);

        \Wayfinder\Database\DB::setResolver(static function (?string $name = null) use ($default, $analytics): \Wayfinder\Database\Database {
            return match ($name) {
                'analytics' => $analytics,
                null => $default,
                default => throw new \RuntimeException('DB resolver not configured.'),
            };
        });

        self::assertSame($default, \db());
        self::assertSame($analytics, \db('analytics'));
    }

    public function test_url_helpers_generate_application_urls(): void
    {
        $generator = new \Wayfinder\Routing\UrlGenerator('http://example.test');
        \Wayfinder\Routing\UrlGenerator::setResolver(static fn (): \Wayfinder\Routing\UrlGenerator => $generator);

        self::assertSame('http://example.test/user/profile', \url('user/profile'));
        self::assertSame('http://example.test/user/profile/1', \url('user/profile', [1]));
        self::assertSame('https://example.test/user/profile', \secure_url('user/profile'));
        self::assertSame('http://example.test/img/photo.jpg', \asset('img/photo.jpg'));
        self::assertSame('http://example.test/img/photo.jpg', \assets('img/photo.jpg'));
        self::assertSame($generator, \url());
    }

    public function test_path_helpers_resolve_application_paths(): void
    {
        $paths = new \Wayfinder\Support\PathResolver('/srv/app');
        \Wayfinder\Support\PathResolver::setResolver(static fn (): \Wayfinder\Support\PathResolver => $paths);

        self::assertSame('/srv/app/composer.json', \base_path('composer.json'));
        self::assertSame('/srv/app/app/Controllers/HomeController.php', \app_path('Controllers/HomeController.php'));
        self::assertSame('/srv/app/config/app.php', \config_path('app.php'));
        self::assertSame('/srv/app/database/migrations', \database_path('migrations'));
        self::assertSame('/srv/app/lang/en/messages.php', \lang_path('en/messages.php'));
        self::assertSame('/srv/app/public/index.php', \public_path('index.php'));
        self::assertSame('/srv/app/resources/views/home.php', \resource_path('views/home.php'));
        self::assertSame('/srv/app/storage/logs/app.log', \storage_path('logs/app.log'));
    }
}
