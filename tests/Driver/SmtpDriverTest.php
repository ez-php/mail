<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Driver\SmtpDriver;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailException;
use EzPhp\Mail\MimeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class SmtpDriverTest
 *
 * Unit tests verify connection-failure behaviour without a live server.
 * Integration tests (group "mailpit") require a running Mailpit instance:
 *
 *   MAILPIT_HOST=127.0.0.1  (default: 127.0.0.1)
 *   MAILPIT_SMTP_PORT=1025  (default: 1025)
 *   MAILPIT_API_PORT=8025   (default: 8025)
 *
 * @package Tests\Driver
 */
#[CoversClass(SmtpDriver::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(MailException::class)]
final class SmtpDriverTest extends TestCase
{
    // ── Unit tests (no network required) ──────────────────────────────────────

    public function testConnectionFailureThrowsMailException(): void
    {
        // Port 1 is reserved and almost never listening — connection refused immediately.
        $driver = new SmtpDriver(
            host: '127.0.0.1',
            port: 1,
            username: '',
            password: '',
            encryption: 'none',
            fromAddress: 'from@example.com',
            fromName: 'Sender',
            mime: new MimeBuilder(),
            timeout: 2,
        );

        $this->expectException(MailException::class);
        $this->expectExceptionMessageMatches('/Cannot connect to SMTP server/');

        $driver->send((new Mailable())->to('to@example.com')->subject('Hi')->text('body'));
    }

    public function testConnectionToNonExistentHostThrowsMailException(): void
    {
        $driver = new SmtpDriver(
            host: 'no-such-host.ez-php.invalid',
            port: 25,
            username: '',
            password: '',
            encryption: 'none',
            fromAddress: 'from@example.com',
            fromName: 'Sender',
            mime: new MimeBuilder(),
            timeout: 2,
        );

        $this->expectException(MailException::class);
        $this->expectExceptionMessageMatches('/Cannot connect to SMTP server/');

        $driver->send((new Mailable())->to('to@example.com')->subject('Hi')->text('body'));
    }

    // ── Mailpit integration tests ──────────────────────────────────────────────

    /**
     * Return the Mailpit host from the environment, or null when Mailpit is not configured.
     */
    private function mailpitHost(): ?string
    {
        $host = getenv('MAILPIT_HOST');

        return ($host !== false && $host !== '') ? $host : null;
    }

    /**
     * @return array{host: string, smtpPort: int, apiPort: int}
     */
    private function mailpitConfig(): array
    {
        return [
            'host' => (string) (getenv('MAILPIT_HOST') ?: '127.0.0.1'),
            'smtpPort' => (int) (getenv('MAILPIT_SMTP_PORT') ?: 1025),
            'apiPort' => (int) (getenv('MAILPIT_API_PORT') ?: 8025),
        ];
    }

    /**
     * Delete all messages from Mailpit so each test starts with a clean inbox.
     *
     * @param string $host
     * @param int    $apiPort
     */
    private function purgeMailpit(string $host, int $apiPort): void
    {
        $ctx = stream_context_create(['http' => ['method' => 'DELETE']]);
        @file_get_contents("http://{$host}:{$apiPort}/api/v1/messages", false, $ctx);
    }

    /**
     * Fetch the list of messages from the Mailpit API.
     *
     * @param string $host
     * @param int    $apiPort
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchMailpitMessages(string $host, int $apiPort): array
    {
        $json = @file_get_contents("http://{$host}:{$apiPort}/api/v1/messages");

        if ($json === false) {
            return [];
        }

        /** @var array{messages?: array<int, array<string, mixed>>} $data */
        $data = json_decode($json, true) ?? [];

        return $data['messages'] ?? [];
    }

    #[Group('mailpit')]
    public function testSendPlainTextViaSmtp(): void
    {
        $config = $this->mailpitConfig();

        if ($this->mailpitHost() === null) {
            $this->markTestSkipped('MAILPIT_HOST not set — start Mailpit and set MAILPIT_HOST to run this test');
        }

        $this->purgeMailpit($config['host'], $config['apiPort']);

        $driver = new SmtpDriver(
            host: $config['host'],
            port: $config['smtpPort'],
            username: '',
            password: '',
            encryption: 'none',
            fromAddress: 'sender@example.com',
            fromName: 'Test Sender',
            mime: new MimeBuilder(),
        );

        $mailable = (new Mailable())
            ->to('recipient@example.com', 'Recipient')
            ->subject('Mailpit Smoke Test')
            ->text('Hello from ez-php/mail SmtpDriver test.');

        $driver->send($mailable);

        // Give Mailpit a moment to process the incoming message
        usleep(200_000);

        $messages = $this->fetchMailpitMessages($config['host'], $config['apiPort']);

        $this->assertCount(1, $messages, 'Mailpit should have received exactly one message');

        $msg = $messages[0];
        $subject = $msg['Subject'] ?? null;
        $this->assertIsString($subject);
        $this->assertSame('Mailpit Smoke Test', $subject);
    }

    #[Group('mailpit')]
    public function testSendHtmlMessageViaSmtp(): void
    {
        $config = $this->mailpitConfig();

        if ($this->mailpitHost() === null) {
            $this->markTestSkipped('MAILPIT_HOST not set — start Mailpit and set MAILPIT_HOST to run this test');
        }

        $this->purgeMailpit($config['host'], $config['apiPort']);

        $driver = new SmtpDriver(
            host: $config['host'],
            port: $config['smtpPort'],
            username: '',
            password: '',
            encryption: 'none',
            fromAddress: 'sender@example.com',
            fromName: '',
            mime: new MimeBuilder(),
        );

        $mailable = (new Mailable())
            ->to('recipient@example.com')
            ->subject('HTML Smoke Test')
            ->html('<p>Hello <strong>world</strong></p>')
            ->text('Hello world');

        $driver->send($mailable);

        usleep(200_000);

        $messages = $this->fetchMailpitMessages($config['host'], $config['apiPort']);

        $this->assertCount(1, $messages, 'Mailpit should have received exactly one message');
        $htmlSubject = $messages[0]['Subject'] ?? null;
        $this->assertIsString($htmlSubject);
        $this->assertSame('HTML Smoke Test', $htmlSubject);
    }

    #[Group('mailpit')]
    public function testSendWithAttachmentViaSmtp(): void
    {
        $config = $this->mailpitConfig();

        if ($this->mailpitHost() === null) {
            $this->markTestSkipped('MAILPIT_HOST not set — start Mailpit and set MAILPIT_HOST to run this test');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ez-mail-attach-');
        assert(is_string($tmpFile));
        file_put_contents($tmpFile, 'attachment data');

        try {
            $this->purgeMailpit($config['host'], $config['apiPort']);

            $driver = new SmtpDriver(
                host: $config['host'],
                port: $config['smtpPort'],
                username: '',
                password: '',
                encryption: 'none',
                fromAddress: 'sender@example.com',
                fromName: '',
                mime: new MimeBuilder(),
            );

            $mailable = (new Mailable())
                ->to('recipient@example.com')
                ->subject('Attachment Smoke Test')
                ->text('See attached file.')
                ->attach($tmpFile, 'data.txt');

            $driver->send($mailable);

            usleep(200_000);

            $messages = $this->fetchMailpitMessages($config['host'], $config['apiPort']);

            $this->assertCount(1, $messages, 'Mailpit should have received exactly one message');
            $attachSubject = $messages[0]['Subject'] ?? null;
            $this->assertIsString($attachSubject);
            $this->assertSame('Attachment Smoke Test', $attachSubject);
        } finally {
            unlink($tmpFile);
        }
    }
}
