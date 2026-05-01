<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Support;

use PHPUnit\Framework\TestCase;
use Wayfinder\Support\PathResolver;

final class PathResolverTest extends TestCase
{
    public function test_resolves_framework_path_conventions(): void
    {
        $paths = new PathResolver('/srv/app');

        self::assertSame('/srv/app', $paths->base());
        self::assertSame('/srv/app/composer.json', $paths->base('composer.json'));
        self::assertSame('/srv/app/app/Controllers/HomeController.php', $paths->app('Controllers/HomeController.php'));
        self::assertSame('/srv/app/config/app.php', $paths->config('app.php'));
        self::assertSame('/srv/app/database/migrations', $paths->database('migrations'));
        self::assertSame('/srv/app/lang/en/messages.php', $paths->lang('en/messages.php'));
        self::assertSame('/srv/app/public/index.php', $paths->public('index.php'));
        self::assertSame('/srv/app/resources/views/home.php', $paths->resource('views/home.php'));
        self::assertSame('/srv/app/storage/logs/app.log', $paths->storage('logs/app.log'));
    }

    public function test_trims_duplicate_path_separators(): void
    {
        $paths = new PathResolver('/srv/app/');

        self::assertSame('/srv/app/storage/logs/app.log', $paths->storage('/logs/app.log'));
    }
}
