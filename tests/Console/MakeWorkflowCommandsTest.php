<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Console;

use PHPUnit\Framework\TestCase;
use Wayfinder\Console\MakeControllerCommand;
use Wayfinder\Console\MakeRequestCommand;
use Wayfinder\Tests\Concerns\UsesTempDirectory;

final class MakeWorkflowCommandsTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        $this->setUpTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTempDirectory();
    }

    public function testMakeControllerCreatesThinControllerStub(): void
    {
        $command = new MakeControllerCommand($this->tempDir . '/app/Controllers', 'App\\Controllers');

        ob_start();
        $exit = $command->handle(['Contact']);
        ob_end_clean();

        $file = $this->tempDir . '/app/Controllers/ContactController.php';

        self::assertSame(0, $exit);
        self::assertFileExists($file);

        $contents = (string) file_get_contents($file);
        self::assertStringContainsString('final class ContactController', $contents);
        self::assertStringContainsString('Keep controllers thin', $contents);
        self::assertStringContainsString('public function __invoke(Request $request): Response', $contents);
    }

    public function testMakeRequestCreatesBoundaryValidationStub(): void
    {
        $command = new MakeRequestCommand($this->tempDir . '/app/Requests', 'App\\Requests');

        ob_start();
        $exit = $command->handle(['StoreContact']);
        ob_end_clean();

        $file = $this->tempDir . '/app/Requests/StoreContactRequest.php';

        self::assertSame(0, $exit);
        self::assertFileExists($file);

        $contents = (string) file_get_contents($file);
        self::assertStringContainsString('final class StoreContactRequest extends FormRequest', $contents);
        self::assertStringContainsString('Keep request validation at the HTTP boundary', $contents);
        self::assertStringContainsString("// 'name' => 'required|string|max:100',", $contents);
    }
}
