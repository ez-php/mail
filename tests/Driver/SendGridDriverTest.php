<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Driver\SendGridDriver;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class SendGridDriverTest
 *
 * SendGridDriver talks directly to curl_* functions (no TransportInterface
 * seam like ez-php/http-client), so send() itself cannot be unit-tested
 * without a live SendGrid account. buildPayload()/buildAttachments() are pure
 * (I/O-free apart from reading attachment files) and are exercised here via
 * Reflection, which is exactly where a malformed payload would originate.
 *
 * @package Tests\Driver
 */
#[CoversClass(SendGridDriver::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(MailException::class)]
final class SendGridDriverTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function buildPayload(SendGridDriver $driver, Mailable $mailable): array
    {
        $method = new \ReflectionMethod($driver, 'buildPayload');

        /** @var array<string, mixed> $result */
        $result = $method->invoke($driver, $mailable);

        return $result;
    }

    public function test_payload_contains_from_to_subject_and_text_content(): void
    {
        $driver = new SendGridDriver('key', 'sender@example.com', 'Sender');
        $mailable = (new Mailable())
            ->to('recipient@example.com', 'Recipient')
            ->subject('Hello')
            ->text('Plain body');

        $payload = $this->buildPayload($driver, $mailable);

        $this->assertSame(['email' => 'sender@example.com', 'name' => 'Sender'], $payload['from']);
        $this->assertSame(
            [['to' => [['email' => 'recipient@example.com', 'name' => 'Recipient']]]],
            $payload['personalizations'],
        );
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame([['type' => 'text/plain', 'value' => 'Plain body']], $payload['content']);
    }

    public function test_mailable_from_overrides_driver_default(): void
    {
        $driver = new SendGridDriver('key', 'default@example.com', 'Default');
        $mailable = (new Mailable())
            ->to('r@example.com')
            ->from('override@example.com', 'Override')
            ->subject('x')
            ->text('body');

        $payload = $this->buildPayload($driver, $mailable);

        $this->assertSame(['email' => 'override@example.com', 'name' => 'Override'], $payload['from']);
    }

    public function test_from_name_is_omitted_when_empty(): void
    {
        $driver = new SendGridDriver('key', 'sender@example.com', '');
        $mailable = (new Mailable())->to('r@example.com')->subject('x')->text('body');

        $payload = $this->buildPayload($driver, $mailable);

        $this->assertSame(['email' => 'sender@example.com'], $payload['from']);
        $this->assertArrayNotHasKey('name', $payload['from']);
    }

    public function test_to_name_is_omitted_when_empty(): void
    {
        $driver = new SendGridDriver('key', 'sender@example.com', 'Sender');
        $mailable = (new Mailable())->to('r@example.com')->subject('x')->text('body');

        $payload = $this->buildPayload($driver, $mailable);

        $this->assertSame([['to' => [['email' => 'r@example.com']]]], $payload['personalizations']);
    }

    public function test_html_and_text_both_produce_content_entries(): void
    {
        $driver = new SendGridDriver('key', 'sender@example.com', '');
        $mailable = (new Mailable())
            ->to('r@example.com')
            ->subject('x')
            ->text('Plain')
            ->html('<p>HTML</p>');

        $payload = $this->buildPayload($driver, $mailable);

        $this->assertSame(
            [
                ['type' => 'text/plain', 'value' => 'Plain'],
                ['type' => 'text/html', 'value' => '<p>HTML</p>'],
            ],
            $payload['content'],
        );
    }

    public function test_payload_has_no_attachments_key_when_no_attachments(): void
    {
        $driver = new SendGridDriver('key', 'sender@example.com', '');
        $mailable = (new Mailable())->to('r@example.com')->subject('x')->text('body');

        $payload = $this->buildPayload($driver, $mailable);

        $this->assertArrayNotHasKey('attachments', $payload);
    }

    public function test_attachment_is_base64_encoded_with_metadata(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sg-attach-');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, 'file contents');

        try {
            $driver = new SendGridDriver('key', 'sender@example.com', '');
            $mailable = (new Mailable())
                ->to('r@example.com')
                ->subject('x')
                ->text('body')
                ->attach($tmpFile, 'data.txt');

            $payload = $this->buildPayload($driver, $mailable);

            $attachments = $payload['attachments'];
            $this->assertIsArray($attachments);
            $this->assertCount(1, $attachments);
            $attachment = $attachments[0];
            $this->assertIsArray($attachment);

            $this->assertSame(base64_encode('file contents'), $attachment['content']);
            $this->assertSame('data.txt', $attachment['filename']);
            $this->assertSame('attachment', $attachment['disposition']);
            $this->assertArrayHasKey('type', $attachment);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_unreadable_attachment_throws_mail_exception(): void
    {
        $driver = new SendGridDriver('key', 'sender@example.com', '');
        $mailable = (new Mailable())
            ->to('r@example.com')
            ->subject('x')
            ->text('body')
            ->attach('/nonexistent/path/does-not-exist.txt', 'x.txt');

        $this->expectException(MailException::class);
        $this->expectExceptionMessageMatches('/Attachment not readable/');

        $this->buildPayload($driver, $mailable);
    }
}
