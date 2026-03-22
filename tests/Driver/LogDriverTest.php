<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Attachment;
use EzPhp\Mail\Driver\LogDriver;
use EzPhp\Mail\Mailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class LogDriverTest
 *
 * @package Tests\Driver
 */
#[CoversClass(LogDriver::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(Attachment::class)]
final class LogDriverTest extends TestCase
{
    private string $logPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = sys_get_temp_dir() . '/ez-php-mail-test-' . uniqid('', true) . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function testSendWritesToLogFile(): void
    {
        $driver = new LogDriver($this->logPath);

        $driver->send(
            (new Mailable())
                ->to('alice@example.com', 'Alice')
                ->subject('Test Subject')
                ->text('Hello Alice')
        );

        $this->assertFileExists($this->logPath);
        $content = file_get_contents($this->logPath);
        $this->assertIsString($content);
        $this->assertStringContainsString('Alice <alice@example.com>', $content);
        $this->assertStringContainsString('Test Subject', $content);
        $this->assertStringContainsString('Text: yes', $content);
        $this->assertStringContainsString('HTML: no', $content);
    }

    public function testSendAppendsToExistingFile(): void
    {
        $driver = new LogDriver($this->logPath);

        $driver->send((new Mailable())->to('a@b.com')->subject('First')->text('body'));
        $driver->send((new Mailable())->to('b@c.com')->subject('Second')->text('body'));

        $content = file_get_contents($this->logPath);
        $this->assertIsString($content);
        $this->assertStringContainsString('First', $content);
        $this->assertStringContainsString('Second', $content);
    }

    public function testSendCreatesDirectoryWhenMissing(): void
    {
        $dir = sys_get_temp_dir() . '/ez-php-mail-test-dir-' . uniqid('', true);
        $path = $dir . '/mail.log';

        $driver = new LogDriver($path);
        $driver->send((new Mailable())->to('a@b.com')->subject('Hi')->text('body'));

        $this->assertFileExists($path);

        // Cleanup
        unlink($path);
        rmdir($dir);
    }

    public function testSendIncludesHtmlIndicator(): void
    {
        $driver = new LogDriver($this->logPath);

        $driver->send(
            (new Mailable())
                ->to('a@b.com')
                ->subject('HTML Mail')
                ->html('<p>Hello</p>')
        );

        $content = file_get_contents($this->logPath);
        $this->assertIsString($content);
        $this->assertStringContainsString('Text: no', $content);
        $this->assertStringContainsString('HTML: yes', $content);
    }

    public function testSendIncludesAttachmentCount(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mail_attach_');
        assert(is_string($tmpFile));
        file_put_contents($tmpFile, 'data');

        try {
            $driver = new LogDriver($this->logPath);

            $driver->send(
                (new Mailable())
                    ->to('a@b.com')
                    ->subject('With attachment')
                    ->text('body')
                    ->attach($tmpFile)
            );

            $content = file_get_contents($this->logPath);
            $this->assertIsString($content);
            $this->assertStringContainsString('Attachments: 1', $content);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testSendWithoutDisplayNameUsesAddress(): void
    {
        $driver = new LogDriver($this->logPath);

        $driver->send(
            (new Mailable())
                ->to('plain@example.com')
                ->subject('Hi')
                ->text('body')
        );

        $content = file_get_contents($this->logPath);
        $this->assertIsString($content);
        $this->assertStringContainsString('To: plain@example.com', $content);
    }
}
