<?php

declare(strict_types=1);

namespace Tests\Driver;

use CURLStringFile;
use EzPhp\Mail\Driver\Http\ApiResponse;
use EzPhp\Mail\Driver\Http\CurlApiTransport;
use EzPhp\Mail\MailException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\TestCase;

/**
 * CurlApiTransport against a `php -S` loopback server (tests/Support/mail-api-server.php):
 * request shape, basic auth, multipart bodies, status passthrough, transport errors and
 * the total timeout — no external network.
 *
 * @package Tests\Driver
 */
#[CoversClass(CurlApiTransport::class)]
#[UsesClass(ApiResponse::class)]
#[UsesClass(MailException::class)]
final class MailCurlApiTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;

    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$port = self::freePort();

        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, dirname(__DIR__) . '/Support/mail-api-server.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['PHP_CLI_SERVER_WORKERS' => '2'],
        );

        if (!is_resource($server)) {
            throw new RuntimeException('Could not start the loopback server.');
        }

        self::$server = $server;
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.2);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50_000);
        }

        throw new RuntimeException('The loopback server did not accept connections within 5 seconds.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }

        self::$server = null;

        parent::tearDownAfterClass();
    }

    public function test_posts_a_string_body_with_headers_and_basic_auth(): void
    {
        $response = (new CurlApiTransport())->post(
            self::url('/echo'),
            '{"to":"a@b.c"}',
            ['Content-Type: application/json', 'X-Test: yes'],
            'api:secret-key',
            5,
            2,
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('', $response->error);
        $echo = self::decode($response);
        $this->assertSame('POST', $echo['method']);
        $this->assertSame('Basic ' . base64_encode('api:secret-key'), $echo['authorization']);
        $this->assertSame('yes', $echo['x_test']);
        $this->assertSame('application/json', $echo['content_type']);
        $this->assertSame('{"to":"a@b.c"}', $echo['raw']);
    }

    public function test_omits_basic_auth_when_empty(): void
    {
        $response = (new CurlApiTransport())->post(self::url('/echo'), 'x', ['Authorization: Bearer t'], '', 5, 2);

        $this->assertSame('Bearer t', self::decode($response)['authorization']);
    }

    public function test_posts_an_array_body_as_multipart_form_data(): void
    {
        $response = (new CurlApiTransport())->post(
            self::url('/echo'),
            ['from' => 'me@example.com', 'attachment[0]' => new CURLStringFile('bytes', 'invoice.pdf', 'application/pdf')],
            [],
            '',
            5,
            2,
        );

        $echo = self::decode($response);
        $this->assertIsString($echo['content_type']);
        $this->assertStringStartsWith('multipart/form-data', $echo['content_type']);
        $this->assertSame(['from' => 'me@example.com'], $echo['post']);
        $this->assertSame(['attachment' => ['invoice.pdf']], $echo['files']);
    }

    public function test_non_2xx_status_and_body_are_passed_through(): void
    {
        $response = (new CurlApiTransport())->post(self::url('/status/422'), 'x', [], '', 5, 2);

        $this->assertSame(422, $response->status);
        $this->assertSame('status 422', $response->body);
        $this->assertSame('', $response->error);
    }

    public function test_connection_failure_is_reported_as_a_transport_error(): void
    {
        $response = (new CurlApiTransport())->post('http://127.0.0.1:' . self::freePort() . '/', 'x', [], '', 2, 1);

        $this->assertSame(0, $response->status);
        $this->assertNotSame('', $response->error);

        $this->expectException(MailException::class);
        $response->assertDelivered('Test API');
    }

    public function test_total_timeout_aborts_a_slow_response(): void
    {
        $start = microtime(true);
        $response = (new CurlApiTransport())->post(self::url('/slow'), 'x', [], '', 1, 1);

        $this->assertLessThan(2.5, microtime(true) - $start);
        $this->assertStringContainsStringIgnoringCase('timed out', $response->error);
    }

    /**
     * @param ApiResponse $response
     *
     * @return array<string, mixed>
     */
    private static function decode(ApiResponse $response): array
    {
        $data = json_decode((string) $response->body, true);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param string $path
     *
     * @return non-empty-string
     */
    private static function url(string $path): string
    {
        return 'http://127.0.0.1:' . self::$port . $path;
    }

    /**
     * @return int
     */
    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new RuntimeException('Could not reserve a port: ' . $errstr);
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
