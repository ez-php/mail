<?php

declare(strict_types=1);

namespace EzPhp\Mail;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Mail\Driver\LogDriver;
use EzPhp\Mail\Driver\MailgunDriver;
use EzPhp\Mail\Driver\NullDriver;
use EzPhp\Mail\Driver\SendGridDriver;
use EzPhp\Mail\Driver\SmtpDriver;

/**
 * Class MailServiceProvider
 *
 * Binds MailerInterface to the driver selected by config/mail.php and wires
 * the static Mail facade in boot().
 *
 * Supported drivers (config key: mail.driver):
 * - 'smtp'     — native SMTP via stream_socket_client()
 * - 'mailgun'  — Mailgun v3 REST API via cURL
 * - 'sendgrid' — SendGrid v3 Mail Send API via cURL
 * - 'log'      — writes a human-readable summary to a log file
 * - 'null'     — silently discards all messages (default)
 *
 * Config keys:
 * | Key               | Type   | Default         | Meaning                              |
 * |-------------------|--------|-----------------|--------------------------------------|
 * | mail.driver       | string | 'null'          | Active driver                        |
 * | mail.host         | string | '127.0.0.1'     | SMTP host                            |
 * | mail.port         | int    | 587             | SMTP port                            |
 * | mail.username     | string | ''              | SMTP username (empty = no auth)      |
 * | mail.password     | string | ''              | SMTP password                        |
 * | mail.encryption   | string | 'tls'           | 'ssl', 'tls', or 'none'             |
 * | mail.from_address | string | ''              | Default sender address               |
 * | mail.from_name    | string | ''              | Default sender display name          |
 * | mail.log_path     | string | ''              | Log file path for the log driver     |
 *
 * @package EzPhp\Mail
 */
final class MailServiceProvider extends ServiceProvider
{
    /**
     * Bind MimeBuilder and MailerInterface to the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(MimeBuilder::class, fn (): MimeBuilder => new MimeBuilder());

        $this->app->bind(MailerInterface::class, function (ContainerInterface $app): MailerInterface {
            $config = $app->make(ConfigInterface::class);
            $driver = $config->get('mail.driver');
            $driver = is_string($driver) ? $driver : 'null';

            $mime = $app->make(MimeBuilder::class);

            return match ($driver) {
                'smtp' => $this->makeSmtpDriver($config, $mime),
                'mailgun' => $this->makeMailgunDriver($config),
                'sendgrid' => $this->makeSendGridDriver($config),
                'log' => $this->makeLogDriver($config),
                default => new NullDriver(),
            };
        });
    }

    /**
     * Wire the static Mail facade to the resolved MailerInterface.
     * Optionally wire the view renderer when a MailViewInterface binding is available.
     *
     * @return void
     */
    public function boot(): void
    {
        Mail::setMailer($this->app->make(MailerInterface::class));

        // Wire view renderer when MailViewInterface is bound in the container.
        // The try/catch handles the case where it is not configured — view() calls
        // on Mailable will then throw MailException at send time with a clear message.
        try {
            /** @var MailViewInterface $renderer */
            $renderer = $this->app->make(MailViewInterface::class);
            Mailable::setViewRenderer($renderer);
        } catch (\Throwable) {
            // MailViewInterface not configured — view() will throw on use
        }
    }

    /**
     * Build an SmtpDriver from the current config.
     *
     * @param ConfigInterface $config
     * @param MimeBuilder     $mime
     *
     * @return SmtpDriver
     */
    private function makeSmtpDriver(ConfigInterface $config, MimeBuilder $mime): SmtpDriver
    {
        $host = $config->get('mail.host');
        $host = is_string($host) && $host !== '' ? $host : '127.0.0.1';

        $port = $config->get('mail.port');
        $port = is_int($port) ? $port : 587;

        $username = $config->get('mail.username');
        $username = is_string($username) ? $username : '';

        $password = $config->get('mail.password');
        $password = is_string($password) ? $password : '';

        $encryption = $config->get('mail.encryption');
        $encryption = is_string($encryption) && $encryption !== '' ? $encryption : 'tls';

        $fromAddress = $config->get('mail.from_address');
        $fromAddress = is_string($fromAddress) ? $fromAddress : '';

        $fromName = $config->get('mail.from_name');
        $fromName = is_string($fromName) ? $fromName : '';

        return new SmtpDriver($host, $port, $username, $password, $encryption, $fromAddress, $fromName, $mime);
    }

    /**
     * Build a MailgunDriver from the current config.
     *
     * @param ConfigInterface $config
     *
     * @return MailgunDriver
     */
    private function makeMailgunDriver(ConfigInterface $config): MailgunDriver
    {
        $domain = $config->get('mail.mailgun_domain');
        $apiKey = $config->get('mail.mailgun_secret');
        $region = $config->get('mail.mailgun_region');
        $fromAddress = $config->get('mail.from_address');
        $fromName = $config->get('mail.from_name');

        return new MailgunDriver(
            is_string($domain) ? $domain : '',
            is_string($apiKey) ? $apiKey : '',
            is_string($fromAddress) ? $fromAddress : '',
            is_string($fromName) ? $fromName : '',
            is_string($region) && $region !== '' ? $region : 'us',
        );
    }

    /**
     * Build a SendGridDriver from the current config.
     *
     * @param ConfigInterface $config
     *
     * @return SendGridDriver
     */
    private function makeSendGridDriver(ConfigInterface $config): SendGridDriver
    {
        $apiKey = $config->get('mail.sendgrid_api_key');
        $fromAddress = $config->get('mail.from_address');
        $fromName = $config->get('mail.from_name');

        return new SendGridDriver(
            is_string($apiKey) ? $apiKey : '',
            is_string($fromAddress) ? $fromAddress : '',
            is_string($fromName) ? $fromName : '',
        );
    }

    /**
     * Build a LogDriver from the current config.
     *
     * @param ConfigInterface $config
     *
     * @return LogDriver
     */
    private function makeLogDriver(ConfigInterface $config): LogDriver
    {
        $path = $config->get('mail.log_path');
        $path = is_string($path) ? $path : '';

        return new LogDriver($path);
    }
}
