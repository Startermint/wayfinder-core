<?php

declare(strict_types=1);

namespace Wayfinder\Console;

final class MakeServiceCommand extends AbstractMakeDataClassCommand
{
    public function name(): string
    {
        return 'make:service';
    }

    public function description(): string
    {
        return 'Create a new service class.';
    }

    protected function directoryName(): string
    {
        return 'Services';
    }

    protected function classSuffix(): string
    {
        return 'Service';
    }

    protected function resourceLabel(): string
    {
        return 'Service';
    }

    protected function template(string $namespace, string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

final class {$className}
{
    /**
     * Use a service for workflow logic: multiple models, side effects,
     * external APIs, or longer business processes.
     */
    public function execute(): mixed
    {
        return null;
    }
}
PHP;
    }
}
