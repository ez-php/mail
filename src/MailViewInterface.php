<?php

declare(strict_types=1);

namespace EzPhp\Mail;

/**
 * Interface MailViewInterface
 *
 * Adapter contract that connects Mailable to a view engine.
 * Bind an implementation in a service provider to enable Mailable::view():
 *
 *   $this->app->bind(MailViewInterface::class, fn () => new class($viewEngine) implements MailViewInterface {
 *       public function render(string $template, array $data = []): string {
 *           return $this->engine->render($template, $data);
 *       }
 *   });
 *
 * When ez-php/view is installed, the ViewServiceProvider can bind this directly.
 *
 * @package EzPhp\Mail
 */
interface MailViewInterface
{
    /**
     * Render the given template with the provided data and return HTML.
     *
     * @param string               $template Template name (e.g. 'emails.welcome').
     * @param array<string, mixed> $data     Template variables.
     *
     * @return string Rendered HTML.
     */
    public function render(string $template, array $data = []): string;
}
