<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Attachment;
use EzPhp\Mail\Driver\Http\ApiResponse;
use EzPhp\Mail\Driver\PostmarkDriver;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * @package Tests\Driver
 */
#[CoversClass(PostmarkDriver::class)]
#[UsesClass(ApiResponse::class)]
#[UsesClass(Attachment::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(MailException::class)]
final class PostmarkDriverTest extends TestCase
{
    public function test_send_posts_the_message_as_json_with_the_server_token(): void
    {
        $transport = new MailFakeApiTransport();
        $driver = new PostmarkDriver('tok', 'noreply@example.com', 'My App', 12, 3, $transport);

        $driver->send((new Mailable())->to('r@example.com', 'Rita')->subject('Hi')->text('Plain')->html('<b>Html</b>'));

        $request = $transport->requests[0];
        $this->assertSame('https://api.postmarkapp.com/email', $request['url']);
        $this->assertContains('X-Postmark-Server-Token: tok', $request['headers']);
        $this->assertSame(12, $request['timeout']);
        $this->assertSame(3, $request['connectTimeout']);
        $this->assertIsString($request['body']);
        $this->assertSame([
            'From' => '"My App" <noreply@example.com>',
            'To' => '"Rita" <r@example.com>',
            'Subject' => 'Hi',
            'TextBody' => 'Plain',
            'HtmlBody' => '<b>Html</b>',
        ], json_decode($request['body'], true));
    }

    public function test_the_mailable_sender_overrides_the_default(): void
    {
        $transport = new MailFakeApiTransport();

        (new PostmarkDriver('tok', 'noreply@example.com', '', transport: $transport))
            ->send((new Mailable())->from('me@example.com')->to('r@example.com')->subject('Hi')->text('x'));

        $this->assertIsString($transport->requests[0]['body']);
        $payload = json_decode($transport->requests[0]['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame('me@example.com', $payload['From']);
        $this->assertSame('r@example.com', $payload['To']);
    }

    public function test_a_display_name_cannot_inject_another_address(): void
    {
        $transport = new MailFakeApiTransport();

        (new PostmarkDriver('tok', 'noreply@example.com', '', transport: $transport))
            ->send((new Mailable())->to('r@example.com', "A\", evil@example.com\r\nB")->subject('Hi')->text('x'));

        $this->assertIsString($transport->requests[0]['body']);
        $payload = json_decode($transport->requests[0]['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame('"A\\", evil@example.comB" <r@example.com>', $payload['To']);
    }

    public function test_attachments_are_base64_encoded(): void
    {
        $transport = new MailFakeApiTransport();
        $mailable = (new Mailable())->to('r@example.com')->subject('Hi')->text('x')
            ->attachData('hello', 'a.txt', 'text/plain');

        (new PostmarkDriver('tok', 'noreply@example.com', '', transport: $transport))->send($mailable);

        $this->assertIsString($transport->requests[0]['body']);
        $payload = json_decode($transport->requests[0]['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame(
            [['Name' => 'a.txt', 'Content' => base64_encode('hello'), 'ContentType' => 'text/plain']],
            $payload['Attachments'],
        );
    }

    public function test_a_rejection_throws(): void
    {
        $driver = new PostmarkDriver('tok', 'a@example.com', '', transport: new MailFakeApiTransport(new ApiResponse(422, '{"Message":"no"}')));

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Postmark rejected the message (HTTP 422)');

        $driver->send((new Mailable())->to('r@example.com')->subject('Hi')->text('x'));
    }
}
