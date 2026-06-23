<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Mail\Driver\NullDriver;
use EzPhp\Mail\Mail;
use EzPhp\Mail\MailerInterface;
use EzPhp\Mail\MailServiceProvider;
use EzPhp\Mail\MimeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Smoke test: MailServiceProvider registers and boots its bindings in a minimal
 * application context without error.
 *
 * @package Tests
 */
#[CoversClass(MailServiceProvider::class)]
#[UsesClass(Mail::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(NullDriver::class)]
final class MailServiceProviderTest extends TestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(MailServiceProvider::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Mail::resetMailer();
        parent::tearDown();
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_mailer_is_bound_and_defaults_to_null_driver(): void
    {
        $mailer = $this->app()->make(MailerInterface::class);

        $this->assertInstanceOf(MailerInterface::class, $mailer);
        $this->assertInstanceOf(NullDriver::class, $mailer);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_mime_builder_is_bound(): void
    {
        $this->assertInstanceOf(MimeBuilder::class, $this->app()->make(MimeBuilder::class));
    }
}
