<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Mail\Console\MakeMailableCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * make:mailable writing into a temp app/ directory; generated code is linted.
 *
 * @package Tests\Console
 */
#[CoversClass(MakeMailableCommand::class)]
final class MakeMailableCommandTest extends TestCase
{
    private string $appPath = '';

    /** @var resource */
    private mixed $errors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appPath = sys_get_temp_dir() . '/ez-make-mailable-' . uniqid();
        $errors = fopen('php://memory', 'w+');
        self::assertIsResource($errors);
        $this->errors = $errors;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->appPath . '/Mail/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->appPath . '/Mail')) {
            rmdir($this->appPath . '/Mail');
        }

        if (is_dir($this->appPath)) {
            rmdir($this->appPath);
        }

        parent::tearDown();
    }

    /**
     * @return array{int, string, string}
     */
    private function runMake(string ...$args): array
    {
        ob_start();
        $code = (new MakeMailableCommand($this->appPath, $this->errors))->handle(array_values($args));
        $output = (string) ob_get_clean();
        rewind($this->errors);

        return [$code, $output, (string) stream_get_contents($this->errors)];
    }

    public function test_creates_a_mailable_subclass(): void
    {
        [$code, $output] = $this->runMake('WelcomeMail');
        $file = $this->appPath . '/Mail/WelcomeMail.php';
        $source = (string) file_get_contents($file);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created: app/Mail/WelcomeMail.php', $output);
        $this->assertStringContainsString('namespace App\\Mail;', $source);
        $this->assertStringContainsString('final class WelcomeMail extends Mailable', $source);

        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $lint, $status);
        $this->assertSame(0, $status, implode("\n", $lint));
    }

    public function test_rejects_invalid_names_and_existing_files(): void
    {
        $this->assertSame(1, $this->runMake()[0]);
        $this->assertSame(1, $this->runMake('9Lives')[0]);

        $this->runMake('WelcomeMail');
        [$code, , $errors] = $this->runMake('WelcomeMail');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Mailable already exists: WelcomeMail.php', $errors);
        $this->assertSame('make:mailable', (new MakeMailableCommand())->getName());
    }
}
