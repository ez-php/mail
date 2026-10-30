<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Mail\Driver\Http\ApiResponse;
use EzPhp\Mail\Driver\MailgunDriver;
use EzPhp\Mail\Driver\PostmarkDriver;
use EzPhp\Mail\Driver\SendGridDriver;
use EzPhp\Mail\Driver\SesDriver;
use EzPhp\Mail\Driver\SmtpDriver;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailServiceProvider;
use EzPhp\Mail\MimeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Driver\MailFakeApiTransport;

/**
 * Class MailTimeoutWiringTest
 *
 * `mail.timeout` reaches every network driver, and the HTTP drivers pass both a
 * total and a connect timeout to their transport. The provider's builders are
 * private, so they are reached via Reflection; the timeouts are observed on a
 * recording ApiTransportInterface.
 *
 * @package Tests
 */
#[CoversClass(MailServiceProvider::class)]
#[CoversClass(SendGridDriver::class)]
#[CoversClass(MailgunDriver::class)]
#[CoversClass(PostmarkDriver::class)]
#[CoversClass(SesDriver::class)]
#[UsesClass(SmtpDriver::class)]
#[UsesClass(MimeBuilder::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(ApiResponse::class)]
final class MailTimeoutWiringTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     *
     * @return ConfigInterface
     */
    private function config(array $values): ConfigInterface
    {
        return new class ($values) implements ConfigInterface {
            /**
             * @param array<string, mixed> $values
             */
            public function __construct(private readonly array $values)
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
        };
    }

    /**
     * @param string               $builder
     * @param array<string, mixed> $config
     *
     * @return object
     */
    private function build(string $builder, array $config): object
    {
        $provider = (new \ReflectionClass(MailServiceProvider::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($provider, $builder);
        $args = in_array($builder, ['makeSmtpDriver', 'makeSesDriver'], true) ? [$this->config($config), new MimeBuilder()] : [$this->config($config)];

        $driver = $method->invoke($provider, ...$args);
        self::assertIsObject($driver);

        return $driver;
    }

    /**
     * @param object $driver
     *
     * @return mixed
     */
    private function timeoutOf(object $driver): mixed
    {
        return (new \ReflectionProperty($driver, 'timeout'))->getValue($driver);
    }

    /**
     * @return void
     */
    public function test_postmark_and_ses_builders_read_their_config(): void
    {
        $postmark = $this->build('makePostmarkDriver', ['mail.postmark_token' => 'tok', 'mail.timeout' => 12]);
        $ses = $this->build('makeSesDriver', [
            'mail.ses_key' => 'AKID',
            'mail.ses_secret' => 'secret',
            'mail.ses_region' => 'eu-west-1',
            'mail.timeout' => 12,
        ]);

        $this->assertInstanceOf(PostmarkDriver::class, $postmark);
        $this->assertSame('tok', (new \ReflectionProperty($postmark, 'token'))->getValue($postmark));
        $this->assertSame(12, $this->timeoutOf($postmark));
        $this->assertInstanceOf(SesDriver::class, $ses);
        $this->assertSame('eu-west-1', (new \ReflectionProperty($ses, 'region'))->getValue($ses));
        $this->assertSame(12, $this->timeoutOf($ses));
    }

    /**
     * @return void
     */
    public function test_configured_timeout_reaches_every_network_driver(): void
    {
        foreach (['makeSmtpDriver', 'makeMailgunDriver', 'makeSendGridDriver'] as $builder) {
            $this->assertSame(12, $this->timeoutOf($this->build($builder, ['mail.timeout' => 12])), $builder);
        }
    }

    /**
     * @return void
     */
    public function test_timeout_defaults_to_30_seconds(): void
    {
        foreach (['makeSmtpDriver', 'makeMailgunDriver', 'makeSendGridDriver'] as $builder) {
            $this->assertSame(30, $this->timeoutOf($this->build($builder, [])), $builder);
        }
    }

    /**
     * @return void
     */
    public function test_http_drivers_set_total_and_connect_timeouts(): void
    {
        $sendGridTransport = new MailFakeApiTransport();
        $mailgunTransport = new MailFakeApiTransport();
        $mailable = (new Mailable())->to('r@example.com')->subject('x')->text('body');

        (new SendGridDriver('key', 'from@example.com', 'From', timeout: 15, connectTimeout: 4, transport: $sendGridTransport))->send($mailable);
        (new MailgunDriver('mg.example.com', 'key', 'from@example.com', 'From', timeout: 15, connectTimeout: 4, transport: $mailgunTransport))->send($mailable);

        foreach (['sendgrid' => $sendGridTransport, 'mailgun' => $mailgunTransport] as $name => $transport) {
            $this->assertSame(15, $transport->requests[0]['timeout'], $name);
            $this->assertSame(4, $transport->requests[0]['connectTimeout'], $name);
        }
    }
}
