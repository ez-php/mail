<?php

declare(strict_types=1);

namespace EzPhp\Mail;

/**
 * Interface MailerInterface
 *
 * Contract for all mail transport drivers.
 * Implementations are responsible for delivering a fully configured Mailable.
 *
 * @package EzPhp\Mail
 */
interface MailerInterface
{
    /**
     * Send the given mailable.
     *
     * @param Mailable $mailable The message to deliver.
     *
     * @throws MailException If delivery fails.
     *
     * @return void
     */
    public function send(Mailable $mailable): void;
}
