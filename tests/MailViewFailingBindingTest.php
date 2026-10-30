<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Mail\Driver\NullDriver;
use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailServiceProvider;
use EzPhp\Mail\MailViewInterface;
use EzPhp\Mail\MimeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;

/**
 * A bound MailViewInterface whose factory throws must surface from boot(), not be
 * reported later as "no view renderer configured".
 *
 * @package Tests
 */
#[CoversClass(MailServiceProvider::class)]
#[UsesClass(Mail::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(NullDriver::class)]
final class MailViewFailingBindingTest extends TestCase
{
    private string $basePath = '';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = sys_get_temp_dir() . '/ez-php-mail-view-' . uniqid('', true);
        mkdir($this->basePath . '/config', 0o777, true);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Mail::resetMailer();
        Mailable::resetViewRenderer();
        @rmdir($this->basePath . '/config');
        @rmdir($this->basePath);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_a_failing_view_binding_surfaces_from_boot(): void
    {
        $app = new Application($this->basePath);
        $app->register(MailViewFailingBindingProvider::class);
        $app->register(MailServiceProvider::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('view engine misconfigured');

        $app->bootstrap();
    }

    /**
     * @return void
     */
    public function test_without_a_view_binding_boot_leaves_the_renderer_unset(): void
    {
        $app = new Application($this->basePath);
        $app->register(MailServiceProvider::class);
        $app->bootstrap();

        $this->expectException(\EzPhp\Mail\MailException::class);

        (new Mailable())->view('emails.welcome')->getHtmlBody();
    }
}

/**
 * Binds a MailViewInterface whose factory throws.
 */
final class MailViewFailingBindingProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(MailViewInterface::class, static function (): MailViewInterface {
            throw new RuntimeException('view engine misconfigured');
        });
    }
}
