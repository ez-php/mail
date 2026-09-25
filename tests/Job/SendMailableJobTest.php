<?php

declare(strict_types=1);

namespace Tests\Job;

use EzPhp\Mail\Job\SendMailableJob;
use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use EzPhp\Queue\Driver\InMemoryDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Spy mailer that captures every sent Mailable.
 */
final class SendMailableJobSpyMailer implements MailerInterface
{
    /** @var list<Mailable> */
    public array $sent = [];

    public function send(Mailable $mailable): void
    {
        $this->sent[] = $mailable;
    }
}

#[CoversClass(SendMailableJob::class)]
#[UsesClass(Mailable::class)]
final class SendMailableJobTest extends TestCase
{
    private SendMailableJobSpyMailer $mailer;

    protected function setUp(): void
    {
        $this->mailer = new SendMailableJobSpyMailer();
        Mail::setMailer($this->mailer);
    }

    protected function tearDown(): void
    {
        Mail::resetMailer();
    }

    public function testHandleSendsTheMailableViaMailFacade(): void
    {
        $mailable = (new Mailable())->to('user@example.com')->subject('Welcome!')->text('Hi');

        $job = new SendMailableJob($mailable);
        $job->handle();

        $this->assertCount(1, $this->mailer->sent);
        $this->assertSame($mailable, $this->mailer->sent[0]);
    }

    public function testJobDefaultsMatchBaseJobConfiguration(): void
    {
        $mailable = (new Mailable())->to('user@example.com')->subject('Welcome!')->text('Hi');
        $job = new SendMailableJob($mailable);

        $this->assertSame('default', $job->getQueue());
        $this->assertSame(3, $job->getMaxTries());
    }

    public function testJobSurvivesAQueueRoundTripAndStillSends(): void
    {
        $queue = new InMemoryDriver();
        $queue->push(new SendMailableJob((new Mailable())->to('user@example.com')->subject('Queued')->text('Hi')));

        $job = $queue->pop();
        $this->assertInstanceOf(SendMailableJob::class, $job);
        $job->handle();

        $this->assertCount(1, $this->mailer->sent);
        $this->assertSame('Queued', $this->mailer->sent[0]->getSubject());
    }
}
