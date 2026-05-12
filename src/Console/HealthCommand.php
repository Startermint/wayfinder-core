<?php

declare(strict_types=1);

namespace Wayfinder\Console;

use Wayfinder\Health\HealthReport;
use Wayfinder\Health\HealthResult;
use Wayfinder\Health\HealthRunner;

final class HealthCommand implements Command
{
    /** @var resource */
    private mixed $output;

    /**
     * @param resource|null $output
     */
    public function __construct(
        private readonly HealthRunner $runner,
        private readonly string $name = 'health',
        mixed $output = null,
    ) {
        $this->output = $output ?? STDOUT;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return 'Run application health and readiness checks.';
    }

    public function handle(array $arguments = []): int
    {
        $json = in_array('--json', $arguments, true);
        $report = $this->runner->run();

        if ($json) {
            fwrite($this->output, json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        } else {
            $this->writeReport($report);
        }

        return $report->hasFailures() ? 1 : 0;
    }

    private function writeReport(HealthReport $report): void
    {
        fwrite($this->output, sprintf("Health status: %s\n", strtoupper($report->status())));

        foreach ($report->results as $result) {
            fwrite($this->output, sprintf(
                "[%s] %s - %s\n",
                strtoupper($result->status),
                $result->name,
                $result->message,
            ));

            if ($result->context !== []) {
                fwrite($this->output, '      ' . json_encode($result->context, JSON_UNESCAPED_SLASHES) . "\n");
            }
        }

        if ($report->status() === HealthResult::WARN) {
            fwrite($this->output, "Warnings do not fail the health command, but should be reviewed before production deploys.\n");
        }
    }
}
