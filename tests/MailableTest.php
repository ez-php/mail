<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Mail\Attachment;
use EzPhp\Mail\Mailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class MailableTest
 *
 * @package Tests
 */
#[CoversClass(Mailable::class)]
#[UsesClass(Attachment::class)]
final class MailableTest extends TestCase
{
    public function testDefaultsAreEmptyStrings(): void
    {
        $mail = new Mailable();

        $this->assertSame('', $mail->getToAddress());
        $this->assertSame('', $mail->getToName());
        $this->assertSame('', $mail->getFromAddress());
        $this->assertSame('', $mail->getFromName());
        $this->assertSame('', $mail->getSubject());
        $this->assertSame('', $mail->getTextBody());
        $this->assertSame('', $mail->getHtmlBody());
        $this->assertSame([], $mail->getAttachments());
    }

    public function testToSetsAddressAndName(): void
    {
        $mail = (new Mailable())->to('alice@example.com', 'Alice');

        $this->assertSame('alice@example.com', $mail->getToAddress());
        $this->assertSame('Alice', $mail->getToName());
    }

    public function testToWithoutNameLeavesNameEmpty(): void
    {
        $mail = (new Mailable())->to('bob@example.com');

        $this->assertSame('bob@example.com', $mail->getToAddress());
        $this->assertSame('', $mail->getToName());
    }

    public function testFromSetsAddressAndName(): void
    {
        $mail = (new Mailable())->from('sender@example.com', 'Sender');

        $this->assertSame('sender@example.com', $mail->getFromAddress());
        $this->assertSame('Sender', $mail->getFromName());
    }

    public function testSubjectSetsValue(): void
    {
        $mail = (new Mailable())->subject('Hello World');

        $this->assertSame('Hello World', $mail->getSubject());
    }

    public function testTextSetsBody(): void
    {
        $mail = (new Mailable())->text('Plain text body');

        $this->assertSame('Plain text body', $mail->getTextBody());
    }

    public function testHtmlSetsBody(): void
    {
        $mail = (new Mailable())->html('<p>HTML body</p>');

        $this->assertSame('<p>HTML body</p>', $mail->getHtmlBody());
    }

    public function testAttachAddsAttachment(): void
    {
        $mail = (new Mailable())->attach('/tmp/file.pdf', 'report.pdf');

        $attachments = $mail->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('/tmp/file.pdf', $attachments[0]->getPath());
        $this->assertSame('report.pdf', $attachments[0]->getName());
    }

    public function testAttachWithoutNameUsesBasename(): void
    {
        $mail = (new Mailable())->attach('/tmp/document.txt');

        $this->assertSame('document.txt', $mail->getAttachments()[0]->getName());
    }

    public function testMultipleAttachmentsAccumulate(): void
    {
        $mail = (new Mailable())
            ->attach('/tmp/a.txt')
            ->attach('/tmp/b.txt');

        $this->assertCount(2, $mail->getAttachments());
    }

    public function testFluentChainingReturnsSameInstance(): void
    {
        $mail = new Mailable();

        $result = $mail->to('a@b.com')
            ->from('s@b.com')
            ->subject('Hi')
            ->text('Hello')
            ->html('<p>Hello</p>');

        $this->assertSame($mail, $result);
    }
}
