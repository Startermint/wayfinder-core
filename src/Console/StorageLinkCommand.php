<?php

declare(strict_types=1);

namespace Wayfinder\Console;

final class StorageLinkCommand implements Command
{
    public function __construct(
        private readonly string $publicPath,
        private readonly string $storagePath,
        private mixed $output = null,
        private mixed $errors = null,
    ) {
        $this->output = $this->output ?? STDOUT;
        $this->errors = $this->errors ?? STDERR;
    }

    public function name(): string
    {
        return 'storage:link';
    }

    public function description(): string
    {
        return 'Create the public storage symlink.';
    }

    public function handle(array $arguments = []): int
    {
        $link = rtrim($this->publicPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage';
        $target = rtrim($this->storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'public';

        if (! is_dir($target) && ! @mkdir($target, 0775, true) && ! is_dir($target)) {
            fwrite($this->errors, sprintf("Unable to create storage directory [%s].\n", $target));

            return 1;
        }

        if (is_link($link)) {
            $existingTarget = readlink($link);
            if ($existingTarget === $target) {
                fwrite($this->output, "The public storage link already exists.\n");

                return 0;
            }

            fwrite($this->errors, sprintf("The public storage link already exists and points to [%s].\n", (string) $existingTarget));

            return 1;
        }

        if (file_exists($link)) {
            fwrite($this->errors, sprintf("The [%s] path already exists and is not a symlink.\n", $link));

            return 1;
        }

        if (! @symlink($target, $link)) {
            fwrite($this->errors, sprintf("Unable to create storage link [%s].\n", $link));

            return 1;
        }

        fwrite($this->output, sprintf("The [%s] link has been connected to [%s].\n", $link, $target));

        return 0;
    }
}
