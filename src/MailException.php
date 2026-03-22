<?php

declare(strict_types=1);

namespace EzPhp\Mail;

/**
 * Class MailException
 *
 * Base exception for all mail-related errors including connection failures,
 * SMTP protocol errors, and attachment read errors.
 *
 * @package EzPhp\Mail
 */
final class MailException extends \RuntimeException
{
}
