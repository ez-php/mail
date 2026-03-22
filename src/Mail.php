<?php

declare(strict_types=1);

namespace EzPhp\Mail;

/**
 * Class Mail
 *
 * Static facade for the active MailerInterface singleton.
 * The MailServiceProvider calls setMailer() in boot(), so all static methods
 * are available after the application is bootstrapped.
 *
 * Usage:
 *   Mail::send((new Mailable())->to('a@b.com')->subject('Hi')->text('Hello'));
 *
 * Testing:
 *   Mail::setMailer($spy); // inject a test double
 *   // ... exercise code under test ...
 *   Mail::resetMailer();   // tear down in tearDown()
 *
 * @package EzPhp\Mail
 */
final class Mail
{
    /**
     * @var MailerInterface|null Active mailer singleton; null before setMailer() is called.
     */
    private static ?MailerInterface $mailer = null;

    /**
     * Replace (or initialise) the active mailer.
     *
     * @param MailerInterface $mailer
     *
     * @return void
     */
    public static function setMailer(MailerInterface $mailer): void
    {
        self::$mailer = $mailer;
    }

    /**
     * Clear the active mailer. Call in test tearDown() to prevent state leaking.
     *
     * @return void
     */
    public static function resetMailer(): void
    {
        self::$mailer = null;
    }

    /**
     * Deliver the given mailable via the active driver.
     *
     * @param Mailable $mailable
     *
     * @throws \RuntimeException When called before setMailer().
     * @throws MailException     When delivery fails.
     *
     * @return void
     */
    public static function send(Mailable $mailable): void
    {
        if (self::$mailer === null) {
            throw new \RuntimeException(
                'Mail facade is not initialised. Add MailServiceProvider to your application.'
            );
        }

        self::$mailer->send($mailable);
    }
}
