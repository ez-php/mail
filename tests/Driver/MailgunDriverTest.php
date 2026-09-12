<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Driver\MailgunDriver;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class MailgunDriverTest
 *
 * MailgunDriver talks directly to curl_* functions (no TransportInterface
 * seam like ez-php/http-client), so send() itself cannot be unit-tested
 * without a live Mailgun account. buildFields() is pure (I/O-free apart from
 * reading attachment files) and is exercised here via Reflection, which is
 * exactly where a malformed payload would originate.
 *
 * @package Tests\Driver
 */
#[CoversClass(MailgunDriver::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(MailException::class)]
final class MailgunDriverTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function buildFields(MailgunDriver $driver, Mailable $mailable): array
    {
        $method = new \ReflectionMethod($driver, 'buildFields');

        /** @var array<string, mixed> $result */
        $result = $method->invoke($driver, $mailable);

        return $result;
    }

    public function test_fields_contain_from_to_subject_and_text(): void
    {
        $driver = new MailgunDriver('mail.example.com', 'key-secret', 'sender@example.com', 'Sender');
        $mailable = (new Mailable())
            ->to('recipient@example.com', 'Recipient')
            ->subject('Hello')
            ->text('Plain body');

        $fields = $this->buildFields($driver, $mailable);

        $this->assertSame('Sender <sender@example.com>', $fields['from']);
        $this->assertSame('Recipient <recipient@example.com>', $fields['to']);
        $this->assertSame('Hello', $fields['subject']);
        $this->assertSame('Plain body', $fields['text']);
        $this->assertArrayNotHasKey('html', $fields);
    }

    public function test_mailable_from_overrides_driver_default(): void
    {
        $driver = new MailgunDriver('mail.example.com', 'key', 'default@example.com', 'Default');
        $mailable = (new Mailable())
            ->to('r@example.com')
            ->from('override@example.com', 'Override')
            ->subject('x')
            ->text('body');

        $fields = $this->buildFields($driver, $mailable);

        $this->assertSame('Override <override@example.com>', $fields['from']);
    }

    public function test_from_without_name_is_bare_address(): void
    {
        $driver = new MailgunDriver('mail.example.com', 'key', 'sender@example.com', '');
        $mailable = (new Mailable())->to('r@example.com')->subject('x')->text('body');

        $fields = $this->buildFields($driver, $mailable);

        $this->assertSame('sender@example.com', $fields['from']);
    }

    public function test_to_without_name_is_bare_address(): void
    {
        $driver = new MailgunDriver('mail.example.com', 'key', 'sender@example.com', 'Sender');
        $mailable = (new Mailable())->to('r@example.com')->subject('x')->text('body');

        $fields = $this->buildFields($driver, $mailable);

        $this->assertSame('r@example.com', $fields['to']);
    }

    public function test_html_body_is_included_when_set(): void
    {
        $driver = new MailgunDriver('mail.example.com', 'key', 'sender@example.com', '');
        $mailable = (new Mailable())
            ->to('r@example.com')
            ->subject('x')
            ->html('<p>HTML</p>');

        $fields = $this->buildFields($driver, $mailable);

        $this->assertSame('<p>HTML</p>', $fields['html']);
        $this->assertArrayNotHasKey('text', $fields);
    }

    public function test_attachment_becomes_indexed_curlfile_field(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mg-attach-');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, 'file contents');

        try {
            $driver = new MailgunDriver('mail.example.com', 'key', 'sender@example.com', '');
            $mailable = (new Mailable())
                ->to('r@example.com')
                ->subject('x')
                ->text('body')
                ->attach($tmpFile, 'data.txt');

            $fields = $this->buildFields($driver, $mailable);

            $this->assertArrayHasKey('attachment[0]', $fields);
            $this->assertInstanceOf(\CURLFile::class, $fields['attachment[0]']);
            $this->assertSame('data.txt', $fields['attachment[0]']->getPostFilename());
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_multiple_attachments_use_sequential_indices(): void
    {
        $tmp1 = tempnam(sys_get_temp_dir(), 'mg-attach-1-');
        $tmp2 = tempnam(sys_get_temp_dir(), 'mg-attach-2-');
        $this->assertIsString($tmp1);
        $this->assertIsString($tmp2);
        file_put_contents($tmp1, 'a');
        file_put_contents($tmp2, 'b');

        try {
            $driver = new MailgunDriver('mail.example.com', 'key', 'sender@example.com', '');
            $mailable = (new Mailable())
                ->to('r@example.com')
                ->subject('x')
                ->text('body')
                ->attach($tmp1, 'one.txt')
                ->attach($tmp2, 'two.txt');

            $fields = $this->buildFields($driver, $mailable);

            $this->assertArrayHasKey('attachment[0]', $fields);
            $this->assertArrayHasKey('attachment[1]', $fields);
        } finally {
            unlink($tmp1);
            unlink($tmp2);
        }
    }

    public function test_unreadable_attachment_throws_mail_exception(): void
    {
        $driver = new MailgunDriver('mail.example.com', 'key', 'sender@example.com', '');
        $mailable = (new Mailable())
            ->to('r@example.com')
            ->subject('x')
            ->text('body')
            ->attach('/nonexistent/path/does-not-exist.txt', 'x.txt');

        $this->expectException(MailException::class);
        $this->expectExceptionMessageMatches('/Attachment not readable/');

        $this->buildFields($driver, $mailable);
    }
}
