<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Mail\Attachment;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailException;
use EzPhp\Mail\MimeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class MimeBuilderTest
 *
 * @package Tests
 */
#[CoversClass(MimeBuilder::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(Attachment::class)]
#[UsesClass(MailException::class)]
final class MimeBuilderTest extends TestCase
{
    private MimeBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new MimeBuilder();
    }

    public function testPlainTextMessage(): void
    {
        $mailable = (new Mailable())
            ->to('alice@example.com', 'Alice')
            ->subject('Hello')
            ->text('Plain text body');

        $message = $this->builder->build($mailable, 'sender@example.com', 'Sender');

        $this->assertStringContainsString('To: Alice <alice@example.com>', $message);
        $this->assertStringContainsString('From: Sender <sender@example.com>', $message);
        $this->assertStringContainsString('Subject: Hello', $message);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $message);
        $this->assertStringContainsString('Content-Transfer-Encoding: quoted-printable', $message);
        $this->assertStringContainsString('Plain text body', $message);
    }

    public function testHtmlOnlyMessage(): void
    {
        $mailable = (new Mailable())
            ->to('bob@example.com')
            ->subject('HTML Mail')
            ->html('<p>Hello</p>');

        $message = $this->builder->build($mailable, 'from@example.com', '');

        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $message);
        $this->assertStringContainsString('Content-Transfer-Encoding: quoted-printable', $message);
        $this->assertStringContainsString('<p>Hello</p>', $message);
    }

    public function testTextAndHtmlProducesMultipartAlternative(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->subject('Alt')
            ->text('Text version')
            ->html('<p>HTML version</p>');

        $message = $this->builder->build($mailable, 'from@example.com', '');

        $this->assertStringContainsString('Content-Type: multipart/alternative; boundary=', $message);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $message);
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $message);
        $this->assertStringContainsString('Text version', $message);
        $this->assertStringContainsString('<p>HTML version</p>', $message);
    }

    public function testAttachmentProducesMultipartMixed(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mail_test_');
        assert(is_string($tmpFile));
        file_put_contents($tmpFile, 'attachment content');

        try {
            $mailable = (new Mailable())
                ->to('user@example.com')
                ->subject('With attachment')
                ->text('See attached')
                ->attach($tmpFile, 'file.txt');

            $message = $this->builder->build($mailable, 'from@example.com', '');

            $this->assertStringContainsString('Content-Type: multipart/mixed; boundary=', $message);
            $this->assertStringContainsString('Content-Transfer-Encoding: base64', $message);
            $this->assertStringContainsString('Content-Disposition: attachment; filename="file.txt"', $message);
            $this->assertStringContainsString(base64_encode('attachment content'), $message);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testAttachmentThrowsWhenFileNotReadable(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->subject('Bad')
            ->text('body')
            ->attach('/nonexistent/path/file.bin');

        $this->expectException(MailException::class);
        $this->expectExceptionMessageMatches('/Cannot read attachment/');

        $this->builder->build($mailable, 'from@example.com', '');
    }

    public function testMailableFromOverridesDefault(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->from('custom@example.com', 'Custom Sender')
            ->subject('Hi')
            ->text('body');

        $message = $this->builder->build($mailable, 'default@example.com', 'Default');

        $this->assertStringContainsString('From: Custom Sender <custom@example.com>', $message);
        $this->assertStringNotContainsString('default@example.com', $message);
    }

    public function testNoDisplayNameInFromHeader(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->subject('Hi')
            ->text('body');

        $message = $this->builder->build($mailable, 'from@example.com', '');

        $this->assertStringContainsString('From: from@example.com', $message);
    }

    public function testNoDisplayNameInToHeader(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->subject('Hi')
            ->text('body');

        $message = $this->builder->build($mailable, 'from@example.com', '');

        $this->assertStringContainsString('To: user@example.com', $message);
    }

    public function testNonAsciiSubjectIsEncoded(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->subject('Betreff: Üniform')
            ->text('body');

        $message = $this->builder->build($mailable, 'from@example.com', '');

        $this->assertStringContainsString('=?UTF-8?B?', $message);
        $this->assertStringNotContainsString('Üniform', $message);
    }

    public function testAsciiSubjectIsNotEncoded(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->subject('Simple ASCII Subject')
            ->text('body');

        $message = $this->builder->build($mailable, 'from@example.com', '');

        $this->assertStringContainsString('Subject: Simple ASCII Subject', $message);
        $this->assertStringNotContainsString('=?UTF-8?B?', $message);
    }

    public function testMessageContainsMimeVersionHeader(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->subject('Hi')
            ->text('body');

        $message = $this->builder->build($mailable, 'from@example.com', '');

        $this->assertStringContainsString('MIME-Version: 1.0', $message);
    }

    public function testMessageContainsDateHeader(): void
    {
        $mailable = (new Mailable())
            ->to('user@example.com')
            ->subject('Hi')
            ->text('body');

        $message = $this->builder->build($mailable, 'from@example.com', '');

        $this->assertMatchesRegularExpression('/Date: \w{3}, \d{2} \w{3} \d{4}/', $message);
    }
}
