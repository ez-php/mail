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
use EzPhp\Testing\ApplicationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * MailServiceProvider::boot() hands a bound MailViewInterface to Mailable, so
 * Mailable::view() renders without any manual setViewRenderer() call.
 *
 * @package Tests
 */
#[CoversClass(MailServiceProvider::class)]
#[UsesClass(Mail::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(NullDriver::class)]
final class MailViewWiringTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(MailViewWiringViewProvider::class);
        $app->register(MailServiceProvider::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Mail::resetMailer();
        Mailable::resetViewRenderer();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_boot_wires_the_bound_view_renderer_into_mailable(): void
    {
        $mail = (new Mailable())->view('emails.welcome', ['name' => 'Alice']);

        $this->assertSame('emails.welcome:Alice', $mail->getHtmlBody());
    }
}

/**
 * Binds a MailViewInterface that echoes the template name and the `name` variable.
 */
final class MailViewWiringViewProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(MailViewInterface::class, fn (): MailViewInterface => new class () implements MailViewInterface {
            public function render(string $template, array $data = []): string
            {
                return $template . ':' . (is_string($data['name'] ?? null) ? $data['name'] : '');
            }
        });
    }
}
