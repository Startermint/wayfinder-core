<?php

declare(strict_types=1);

namespace Wayfinder\Console;

final class MakeQueueTableCommand implements Command
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    public function name(): string
    {
        return 'make:queue-table';
    }

    public function description(): string
    {
        return 'Create a migration for the jobs table.';
    }

    public function handle(array $arguments = []): int
    {
        if (! is_dir($this->path) && ! mkdir($this->path, 0777, true) && ! is_dir($this->path)) {
            fwrite(STDERR, sprintf("Unable to create migration directory [%s].\n", $this->path));

            return 1;
        }

        $normalized = 'create_jobs_table';
        $existing = glob(rtrim($this->path, '/') . '/*_' . $normalized . '.php') ?: [];

        if ($existing !== []) {
            fwrite(STDERR, "Jobs table migration already exists.\n");

            return 1;
        }

        $filename = sprintf('%s_%s.php', date('YmdHis'), $normalized);
        $target = rtrim($this->path, '/') . '/' . $filename;

        $template = <<<'PHP'
<?php

declare(strict_types=1);

use Wayfinder\Database\Database;
use Wayfinder\Database\Migration;

return new class implements Migration
{
    public function up(Database $database): void
    {
        $database->statement(<<<'SQL'
            CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                reserved_at INTEGER NULL,
                available_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            )
        SQL);
    }

    public function down(Database $database): void
    {
        $database->statement('DROP TABLE IF EXISTS jobs');
    }
};
PHP;

        if (file_put_contents($target, $template . "\n") === false) {
            fwrite(STDERR, sprintf("Unable to write migration [%s].\n", $filename));

            return 1;
        }

        fwrite(STDOUT, sprintf("Created queue table migration: %s\n", $filename));

        return 0;
    }
}
