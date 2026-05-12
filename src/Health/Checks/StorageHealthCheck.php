<?php

declare(strict_types=1);

namespace Wayfinder\Health\Checks;

use League\Flysystem\FilesystemOperator;
use Wayfinder\Health\HealthCheck;
use Wayfinder\Health\HealthResult;

final readonly class StorageHealthCheck implements HealthCheck
{
    public function __construct(
        private FilesystemOperator $disk,
        private string $name = 'storage',
        private string $pathPrefix = '.health',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthResult
    {
        $path = trim($this->pathPrefix, '/') . '/' . bin2hex(random_bytes(8)) . '.txt';
        $value = 'ok-' . bin2hex(random_bytes(8));

        $this->disk->write($path, $value);
        $stored = $this->disk->read($path);
        $this->disk->delete($path);

        if ($stored !== $value) {
            return HealthResult::fail($this->name, 'Storage write/read check failed.');
        }

        return HealthResult::ok($this->name, 'Storage write/read/delete check succeeded.');
    }
}
