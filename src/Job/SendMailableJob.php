<?php

declare(strict_types=1);

namespace EzPhp\Mail\Job;

use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;
use EzPhp\Queue\Job;

/**
 * Class SendMailableJob
 *
 * Queue Job that delivers a pre-built Mailable via Mail::send().
 *
 * Mirrors ez-php/notification's SendMailNotificationJob one layer down, so
 * applications that want queued mail delivery without going through the
 * notification module don't have to hand-roll this wrapper themselves.
 *
 * Requires ez-php/queue (soft dependency — declared in require-dev only;
 * this class is only autoloaded when actually referenced).
 *
 * @package EzPhp\Mail\Job
 */
final class SendMailableJob extends Job
{
    /**
     * SendMailableJob Constructor
     *
     * @param Mailable $mailable The pre-built mail message to deliver.
     */
    public function __construct(private readonly Mailable $mailable)
    {
    }

    /**
     * Send the mailable via Mail::send().
     *
     * @return void
     */
    public function handle(): void
    {
        Mail::send($this->mailable);
    }
}
