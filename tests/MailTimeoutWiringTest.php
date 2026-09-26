<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Mail\Driver\MailgunDriver;
use EzPhp\Mail\Driver\SendGridDriver;
use EzPhp\Mail\Driver\SmtpDriver;
use EzPhp\Mail\MailServiceProvider;
use EzPhp\Mail\MimeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class MailTimeoutWiringTest
 *
 * `mail.timeout` reaches every network driver, and the HTTP drivers set both a
 * total and a connect timeout on their cURL handle. The builders and the option
 * list are private, so they are reached via Reflection (the drivers call curl_*
 * directly and have no transport seam).
 *
 * @package Tests
 */
#[CoversClass(MailServiceProvider::class)]
#[CoversClass(SendGridDriver::class)]
#[CoversClass(MailgunDriver::class)]
#[UsesClass(SmtpDriver::class)]
#[UsesClass(MimeBuilder::class)]
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
        $args = $builder === 'makeSmtpDriver' ? [$this->config($config), new MimeBuilder()] : [$this->config($config)];

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
        $drivers = [
            new SendGridDriver('key', 'from@example.com', 'From', timeout: 15, connectTimeout: 4),
            new MailgunDriver('mg.example.com', 'key', 'from@example.com', 'From', timeout: 15, connectTimeout: 4),
        ];

        foreach ($drivers as $driver) {
            $options = (new \ReflectionMethod($driver, 'timeoutOptions'))->invoke($driver);

            $this->assertSame([CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 4], $options, $driver::class);
        }
    }
}
