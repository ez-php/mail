<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Mail\Attachment;
use EzPhp\Mail\MailException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class AttachmentTest
 *
 * @package Tests
 */
#[CoversClass(Attachment::class)]
#[UsesClass(MailException::class)]
final class AttachmentTest extends TestCase
{
    public function testGetPathReturnsConstructorValue(): void
    {
        $attachment = new Attachment('/tmp/file.pdf', '');

        $this->assertSame('/tmp/file.pdf', $attachment->getPath());
    }

    public function testGetNameReturnsExplicitName(): void
    {
        $attachment = new Attachment('/tmp/file.pdf', 'report.pdf');

        $this->assertSame('report.pdf', $attachment->getName());
    }

    public function testGetNameFallsBackToBasename(): void
    {
        $attachment = new Attachment('/tmp/some-file.txt', '');

        $this->assertSame('some-file.txt', $attachment->getName());
    }

    public function testGetNameUsesBasenameWhenNameIsEmptyString(): void
    {
        $attachment = new Attachment('/path/to/image.png', '');

        $this->assertSame('image.png', $attachment->getName());
    }

    public function testFromDataKeepsContentNameAndMimeType(): void
    {
        $attachment = Attachment::fromData('bytes', 'report.csv', 'text/csv');

        $this->assertSame('bytes', $attachment->getData());
        $this->assertSame('bytes', $attachment->getContent());
        $this->assertSame('report.csv', $attachment->getName());
        $this->assertSame('text/csv', $attachment->getMimeType());
        $this->assertSame('', $attachment->getPath());
    }

    public function testFromDataRequiresAName(): void
    {
        $this->expectException(MailException::class);

        Attachment::fromData('bytes', '');
    }

    public function testFileAttachmentReadsContentAndDetectsMimeType(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'att-');
        $this->assertIsString($tmp);
        file_put_contents($tmp, 'plain text');

        try {
            $attachment = new Attachment($tmp, 'a.txt');

            $this->assertNull($attachment->getData());
            $this->assertSame('plain text', $attachment->getContent());
            $this->assertSame('text/plain', $attachment->getMimeType());
        } finally {
            unlink($tmp);
        }
    }

    public function testUnreadableFileThrowsAndFallsBackToOctetStream(): void
    {
        $attachment = new Attachment('/nonexistent/file.bin', '');

        $this->assertSame('application/octet-stream', $attachment->getMimeType());

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Cannot read attachment file: /nonexistent/file.bin');

        $attachment->getContent();
    }
}
