<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;

/**
 * Class NullDriver
 *
 * Discards every message silently. Used as the default driver and in tests
 * where mail delivery must not occur.
 *
 * @package EzPhp\Mail\Driver
 */
final class NullDriver implements MailerInterface
{
    /**
     * Accept and silently discard the given mailable.
     *
     * @param Mailable $mailable
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        // intentional no-op
    }
}
