<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use EzPhp\Mail\Driver\Http\ApiTransportInterface;
use EzPhp\Mail\Driver\Http\CurlApiTransport;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use EzPhp\Mail\MailException;

/**
 * Class PostmarkDriver
 *
 * Delivers mail via the Postmark Email API, without a third-party library.
 *
 * API endpoint: https://api.postmarkapp.com/email
 *
 * Authentication: the server token in the X-Postmark-Server-Token header. The
 * request body is JSON; attachments are base64-encoded inline.
 *
 * @package EzPhp\Mail\Driver
 */
final class PostmarkDriver implements MailerInterface
{
    private const string ENDPOINT = 'https://api.postmarkapp.com/email';

    private readonly ApiTransportInterface $transport;

    /**
     * @param string $token       Postmark server token.
     * @param string $fromAddress Default sender address (overridden by Mailable::from()).
     * @param string $fromName    Default sender display name (overridden by Mailable::from()).
     * @param int    $timeout        Total request timeout in seconds (`mail.timeout`).
     * @param int    $connectTimeout Connection-phase timeout in seconds.
     * @param ApiTransportInterface|null $transport HTTP transport; null = CurlApiTransport.
     */
    public function __construct(
        private readonly string $token,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        ?ApiTransportInterface $transport = null,
    ) {
        $this->transport = $transport ?? new CurlApiTransport();
    }

    /**
     * Deliver the given mailable via the Postmark Email API.
     *
     * @param Mailable $mailable
     *
     * @throws MailException On transport errors, unreadable attachments, or non-2xx responses.
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $body = json_encode($this->buildPayload($mailable), JSON_THROW_ON_ERROR);

        $this->transport
            ->post(
                self::ENDPOINT,
                $body,
                ['X-Postmark-Server-Token: ' . $this->token, 'Content-Type: application/json', 'Accept: application/json'],
                '',
                $this->timeout,
                $this->connectTimeout,
            )
            ->assertDelivered('Postmark');
    }

    /**
     * Build the JSON payload for the Postmark API.
     *
     * @param Mailable $mailable
     *
     * @throws MailException When an attachment file is not readable.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Mailable $mailable): array
    {
        $fromAddress = $mailable->getFromAddress() !== '' ? $mailable->getFromAddress() : $this->fromAddress;
        $fromName = $mailable->getFromName() !== '' ? $mailable->getFromName() : $this->fromName;

        $payload = [
            'From' => self::address($fromAddress, $fromName),
            'To' => self::address($mailable->getToAddress(), $mailable->getToName()),
            'Subject' => $mailable->getSubject(),
        ];

        if ($mailable->getTextBody() !== '') {
            $payload['TextBody'] = $mailable->getTextBody();
        }

        if ($mailable->getHtmlBody() !== '') {
            $payload['HtmlBody'] = $mailable->getHtmlBody();
        }

        $attachments = [];

        foreach ($mailable->getAttachments() as $attachment) {
            $attachments[] = [
                'Name' => $attachment->getName(),
                'Content' => base64_encode($attachment->getContent()),
                'ContentType' => $attachment->getMimeType(),
            ];
        }

        if ($attachments !== []) {
            $payload['Attachments'] = $attachments;
        }

        return $payload;
    }

    /**
     * Format an address field. Postmark parses it as an RFC 5322 address list,
     * so a display name must stay one quoted string and cannot add a recipient.
     *
     * @param string $address
     * @param string $name
     *
     * @return string
     */
    private static function address(string $address, string $name): string
    {
        if ($name === '') {
            return $address;
        }

        $quoted = addcslashes(str_replace(["\r", "\n"], '', $name), '"\\');

        return "\"{$quoted}\" <{$address}>";
    }
}
