<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Spy mailer that records every message passed to send().
 */
final class SpyMailer implements MailerInterface
{
    /** @var list<Mailable> */
    private array $sent = [];

    public function send(Mailable $mailable): void
    {
        $this->sent[] = $mailable;
    }

    /**
     * @return list<Mailable>
     */
    public function getSent(): array
    {
        return $this->sent;
    }
}

/**
 * Class MailTest
 *
 * @package Tests
 */
#[CoversClass(Mail::class)]
#[UsesClass(Mailable::class)]
final class MailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::resetMailer();
    }

    protected function tearDown(): void
    {
        Mail::resetMailer();
        parent::tearDown();
    }

    public function testSendDelegatesToMailer(): void
    {
        $spy = new SpyMailer();
        Mail::setMailer($spy);

        $mailable = (new Mailable())->to('a@b.com')->subject('Hi')->text('body');
        Mail::send($mailable);

        $this->assertCount(1, $spy->getSent());
        $this->assertSame($mailable, $spy->getSent()[0]);
    }

    public function testSendThrowsWhenNoMailerSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not initialised/');

        Mail::send(new Mailable());
    }

    public function testResetMailerClearsInstance(): void
    {
        Mail::setMailer(new SpyMailer());
        Mail::resetMailer();

        $this->expectException(\RuntimeException::class);
        Mail::send(new Mailable());
    }

    public function testSetMailerCanReplaceExistingMailer(): void
    {
        $first = new SpyMailer();
        $second = new SpyMailer();

        Mail::setMailer($first);
        Mail::setMailer($second);
        Mail::send(new Mailable());

        $this->assertCount(0, $first->getSent());
        $this->assertCount(1, $second->getSent());
    }
}
