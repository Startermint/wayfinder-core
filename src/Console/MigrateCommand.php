<?php

declare(strict_types=1);

namespace Wayfinder\Console;

use Wayfinder\Database\Migrator;

final class MigrateCommand implements Command
{
    private mixed $input;

    private mixed $output;

    public function __construct(
        private readonly Migrator $migrator,
        private readonly ?string $environment = null,
        mixed $input = null,
        mixed $output = null,
    ) {
        $this->input = $input ?? STDIN;
        $this->output = $output ?? STDOUT;
    }

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run all pending database migrations.';
    }

    public function handle(array $arguments = []): int
    {
        if (in_array('--pretend', $arguments, true)) {
            return $this->handlePretend();
        }

        if ($this->isProduction() && ! in_array('--force', $arguments, true)) {
            $pending = $this->migrator->pendingWithoutCreatingRepository();

            if ($pending === []) {
                $this->write("No pending migrations.\n");

                return 0;
            }

            $this->write("Application is in production.\n");
            $this->write("Pending migrations:\n");

            foreach ($pending as $migration) {
                $this->write(sprintf("- %s\n", $migration));
            }

            $this->write("Tip: run `php wayfinder migrate --pretend` first to inspect SQL.\n");
            $this->write(sprintf("Run %d pending %s in production? [y/N] ", count($pending), count($pending) === 1 ? 'migration' : 'migrations'));

            if (! $this->confirmed()) {
                $this->write("Migration cancelled.\n");

                return 1;
            }
        }

        $ran = $this->migrator->run();

        if ($ran === []) {
            $this->write("No pending migrations.\n");

            return 0;
        }

        foreach ($ran as $migration) {
            $this->write(sprintf("Migrated: %s\n", $migration));
        }

        return 0;
    }

    private function handlePretend(): int
    {
        $migrations = $this->migrator->pretend();

        if ($migrations === []) {
            $this->write("No pending migrations.\n");

            return 0;
        }

        foreach ($migrations as $migration => $statements) {
            $this->write(sprintf("Pretending: %s\n", $migration));

            if ($statements === []) {
                $this->write("  -- No SQL statements were generated.\n");

                continue;
            }

            foreach ($statements as $statement) {
                $this->write(sprintf("  %s\n", $statement['sql']));

                if ($statement['bindings'] !== []) {
                    $this->write(sprintf("  -- bindings: %s\n", json_encode($statement['bindings'])));
                }
            }
        }

        return 0;
    }

    private function isProduction(): bool
    {
        return strtolower($this->environment ?? $this->currentEnvironment()) === 'production';
    }

    private function currentEnvironment(): string
    {
        $env = getenv('APP_ENV');

        if (is_string($env) && $env !== '') {
            return $env;
        }

        return (string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'local');
    }

    private function confirmed(): bool
    {
        $response = fgets($this->input);

        if ($response === false) {
            return false;
        }

        return in_array(strtolower(trim($response)), ['y', 'yes'], true);
    }

    private function write(string $message): void
    {
        fwrite($this->output, $message);
    }
}
