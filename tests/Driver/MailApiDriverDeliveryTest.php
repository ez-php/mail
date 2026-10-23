<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Attachment;
use EzPhp\Mail\Driver\Http\ApiResponse;
use EzPhp\Mail\Driver\MailgunDriver;
use EzPhp\Mail\Driver\SendGridDriver;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * send() of the HTTP API drivers through a fake ApiTransportInterface: the
 * request each driver builds, and the transport-error / non-2xx failure paths
 * that were untestable while the drivers called curl_* directly.
 *
 * @package Tests\Driver
 */
#[CoversClass(MailgunDriver::class)]
#[CoversClass(SendGridDriver::class)]
#[CoversClass(ApiResponse::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(Attachment::class)]
#[UsesClass(MailException::class)]
final class MailApiDriverDeliveryTest extends TestCase
{
    private function mailable(): Mailable
    {
        return (new Mailable())->to('r@example.com')->subject('Hi')->text('Body');
    }

    /**
     * @return array<string, array{\Closure(MailFakeApiTransport): (MailgunDriver|SendGridDriver), string}>
     */
    public static function drivers(): array
    {
        return [
            'mailgun' => [static fn (MailFakeApiTransport $t): MailgunDriver => new MailgunDriver('mg.example.com', 'key-1', 'f@example.com', 'F', transport: $t), 'Mailgun'],
            'sendgrid' => [static fn (MailFakeApiTransport $t): SendGridDriver => new SendGridDriver('SG.key', 'f@example.com', 'F', transport: $t), 'SendGrid'],
        ];
    }

    public function test_mailgun_posts_form_fields_with_basic_auth_to_the_us_endpoint(): void
    {
        $transport = new MailFakeApiTransport();

        (new MailgunDriver('mg.example.com', 'key-1', 'f@example.com', 'F', transport: $transport))->send($this->mailable());

        $this->assertCount(1, $transport->requests);
        $request = $transport->requests[0];
        $this->assertSame('https://api.mailgun.net/v3/mg.example.com/messages', $request['url']);
        $this->assertSame('api:key-1', $request['basicAuth']);
        $this->assertIsArray($request['body']);
        $this->assertSame('Hi', $request['body']['subject']);
    }

    public function test_mailgun_eu_region_uses_the_eu_endpoint(): void
    {
        $transport = new MailFakeApiTransport();

        (new MailgunDriver('mg.example.com', 'key', 'f@example.com', 'F', region: 'eu', transport: $transport))->send($this->mailable());

        $this->assertSame('https://api.eu.mailgun.net/v3/mg.example.com/messages', $transport->requests[0]['url']);
    }

    public function test_sendgrid_posts_json_with_bearer_auth(): void
    {
        $transport = new MailFakeApiTransport(new ApiResponse(202, ''));

        (new SendGridDriver('SG.key', 'f@example.com', 'F', transport: $transport))->send($this->mailable());

        $request = $transport->requests[0];
        $this->assertSame('https://api.sendgrid.com/v3/mail/send', $request['url']);
        $this->assertContains('Authorization: Bearer SG.key', $request['headers']);
        $this->assertSame('', $request['basicAuth']);
        $this->assertIsString($request['body']);
        $decoded = json_decode($request['body'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('Hi', $decoded['subject']);
    }

    public function test_sendgrid_eu_region_uses_the_eu_endpoint(): void
    {
        $transport = new MailFakeApiTransport(new ApiResponse(202, ''));

        (new SendGridDriver('SG.key', 'f@example.com', 'F', transport: $transport, region: 'eu'))->send($this->mailable());

        $this->assertSame('https://api.eu.sendgrid.com/v3/mail/send', $transport->requests[0]['url']);
    }

    /**
     * @param \Closure(MailFakeApiTransport): (MailgunDriver|SendGridDriver) $factory
     */
    #[DataProvider('drivers')]
    public function test_transport_error_throws(\Closure $factory, string $provider): void
    {
        $driver = $factory(new MailFakeApiTransport(new ApiResponse(0, null, 'Could not resolve host')));

        $this->expectException(MailException::class);
        $this->expectExceptionMessage("{$provider} delivery failed: Could not resolve host");

        $driver->send($this->mailable());
    }

    /**
     * @param \Closure(MailFakeApiTransport): (MailgunDriver|SendGridDriver) $factory
     */
    #[DataProvider('drivers')]
    public function test_non_2xx_status_throws_with_status_and_body(\Closure $factory, string $provider): void
    {
        $driver = $factory(new MailFakeApiTransport(new ApiResponse(401, 'Forbidden')));

        $this->expectException(MailException::class);
        $this->expectExceptionMessage("{$provider} rejected the message (HTTP 401): Forbidden");

        $driver->send($this->mailable());
    }

    /**
     * @param \Closure(MailFakeApiTransport): (MailgunDriver|SendGridDriver) $factory
     */
    #[DataProvider('drivers')]
    public function test_missing_body_throws(\Closure $factory, string $provider): void
    {
        $driver = $factory(new MailFakeApiTransport(new ApiResponse(200, null)));

        $this->expectException(MailException::class);
        $this->expectExceptionMessage("{$provider} returned an unexpected non-string response.");

        $driver->send($this->mailable());
    }

    public function test_mailgun_sends_in_memory_attachment_as_curl_string_file(): void
    {
        $transport = new MailFakeApiTransport();

        (new MailgunDriver('mg.example.com', 'key', 'f@example.com', 'F', transport: $transport))
            ->send($this->mailable()->attachData('%PDF-1.7', 'invoice.pdf', 'application/pdf'));

        $body = $transport->requests[0]['body'];
        $this->assertIsArray($body);
        $file = $body['attachment[0]'];
        $this->assertInstanceOf(\CURLStringFile::class, $file);
        $this->assertSame('%PDF-1.7', $file->data);
        $this->assertSame('invoice.pdf', $file->postname);
        $this->assertSame('application/pdf', $file->mime);
    }

    public function test_sendgrid_sends_in_memory_attachment_base64_encoded(): void
    {
        $transport = new MailFakeApiTransport(new ApiResponse(202, ''));

        (new SendGridDriver('SG.key', 'f@example.com', 'F', transport: $transport))
            ->send($this->mailable()->attachData('%PDF-1.7', 'invoice.pdf', 'application/pdf'));

        $body = $transport->requests[0]['body'];
        $this->assertIsString($body);
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded);
        $this->assertSame(
            [['content' => base64_encode('%PDF-1.7'), 'type' => 'application/pdf', 'filename' => 'invoice.pdf', 'disposition' => 'attachment']],
            $decoded['attachments'],
        );
    }
}
