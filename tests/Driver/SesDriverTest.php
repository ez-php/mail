<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Driver\Http\ApiResponse;
use EzPhp\Mail\Driver\Http\SigV4Signer;
use EzPhp\Mail\Driver\SesDriver;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailException;
use EzPhp\Mail\MimeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * @package Tests\Driver
 */
#[CoversClass(SesDriver::class)]
#[CoversClass(SigV4Signer::class)]
#[UsesClass(ApiResponse::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(MailException::class)]
#[UsesClass(MimeBuilder::class)]
final class SesDriverTest extends TestCase
{
    public function test_signer_matches_the_aws_sigv4_test_suite_post_vanilla_vector(): void
    {
        $signer = new SigV4Signer('AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', 'us-east-1', 'service');

        $this->assertSame(
            'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20150830/us-east-1/service/aws4_request, '
            . 'SignedHeaders=host;x-amz-date, '
            . 'Signature=5da7c1a2acd57cee7505fc6676e4e544621c30862966e37dddb68e92efbe5d6b',
            $signer->authorization('POST', 'example.amazonaws.com', '/', '20150830T123600Z', ''),
        );
    }

    private function driver(MailFakeApiTransport $transport): SesDriver
    {
        return new SesDriver(
            'AKID',
            'secret',
            'eu-west-1',
            new MimeBuilder(),
            'noreply@example.com',
            'My App',
            12,
            3,
            $transport,
            static fn (): int => 1_440_938_160,
        );
    }

    public function test_send_posts_the_raw_mime_message_to_the_regional_endpoint(): void
    {
        $transport = new MailFakeApiTransport();

        $this->driver($transport)->send((new Mailable())->to('r@example.com')->subject('Hi there')->text('Body'));

        $request = $transport->requests[0];
        $this->assertSame('https://email.eu-west-1.amazonaws.com/v2/email/outbound-emails', $request['url']);
        $this->assertSame(12, $request['timeout']);
        $this->assertSame(3, $request['connectTimeout']);
        $this->assertIsString($request['body']);
        $payload = json_decode($request['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame(['ToAddresses' => ['r@example.com']], $payload['Destination']);
        $this->assertIsArray($payload['Content']);
        $this->assertIsArray($payload['Content']['Raw']);
        $this->assertIsString($payload['Content']['Raw']['Data']);
        $raw = (string) base64_decode($payload['Content']['Raw']['Data'], true);
        $this->assertStringContainsString('Subject: Hi there', $raw);
        $this->assertStringContainsString('noreply@example.com', $raw);
    }

    public function test_send_signs_the_exact_body_with_sigv4(): void
    {
        $transport = new MailFakeApiTransport();

        $this->driver($transport)->send((new Mailable())->to('r@example.com')->subject('Hi')->text('Body'));

        $request = $transport->requests[0];
        $this->assertIsString($request['body']);
        $this->assertContains('X-Amz-Date: 20150830T123600Z', $request['headers']);
        $this->assertContains('Host: email.eu-west-1.amazonaws.com', $request['headers']);
        $this->assertContains(
            'Authorization: ' . (new SigV4Signer('AKID', 'secret', 'eu-west-1', 'ses'))->authorization(
                'POST',
                'email.eu-west-1.amazonaws.com',
                '/v2/email/outbound-emails',
                '20150830T123600Z',
                $request['body'],
            ),
            $request['headers'],
        );
    }

    public function test_an_invalid_region_is_rejected_before_it_reaches_the_url(): void
    {
        $this->expectException(MailException::class);

        new SesDriver('AKID', 'secret', 'evil.example.com/#', new MimeBuilder(), 'a@example.com', '');
    }

    public function test_a_rejection_throws(): void
    {
        $driver = $this->driver(new MailFakeApiTransport(new ApiResponse(400, '{"message":"no"}')));

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('SES rejected the message (HTTP 400)');

        $driver->send((new Mailable())->to('r@example.com')->subject('Hi')->text('x'));
    }
}
