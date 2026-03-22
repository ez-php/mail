<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Mail\Attachment;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class AttachmentTest
 *
 * @package Tests
 */
#[CoversClass(Attachment::class)]
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
}
