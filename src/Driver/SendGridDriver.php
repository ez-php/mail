<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use EzPhp\Mail\Attachment;
use EzPhp\Mail\Driver\Http\ApiTransportInterface;
use EzPhp\Mail\Driver\Http\CurlApiTransport;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use EzPhp\Mail\MailException;

/**
 * Class SendGridDriver
 *
 * Delivers mail via the SendGrid Mail Send API (v3).
 * No third-party library is required — the driver uses PHP's built-in cURL extension.
 *
 * API endpoint: https://api.sendgrid.com/v3/mail/send
 *
 * Authentication: Bearer token in the Authorization header using the SendGrid API key.
 * The request body is JSON; attachments are base64-encoded inline.
 *
 * The HTTP request goes through an ApiTransportInterface (CurlApiTransport by
 * default), so the error paths are unit-tested with a fake transport; real
 * delivery still needs a SendGrid account and a verified Sender identity.
 *
 * @package EzPhp\Mail\Driver
 */
final class SendGridDriver implements MailerInterface
{
    private const string ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    private readonly ApiTransportInterface $transport;

    /**
     * @param string $apiKey      SendGrid API key with the Mail Send permission.
     * @param string $fromAddress Default sender address (overridden by Mailable::from()).
     * @param string $fromName    Default sender display name (overridden by Mailable::from()).
     * @param int    $timeout        Total request timeout in seconds (`mail.timeout`).
     * @param int    $connectTimeout Connection-phase timeout in seconds, so an unreachable API fails fast.
     * @param ApiTransportInterface|null $transport HTTP transport; null = CurlApiTransport.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        ?ApiTransportInterface $transport = null,
    ) {
        $this->transport = $transport ?? new CurlApiTransport();
    }

    /**
     * Deliver the given mailable via the SendGrid v3 Mail Send API.
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
                ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json', 'Accept: application/json'],
                '',
                $this->timeout,
                $this->connectTimeout,
            )
            ->assertDelivered('SendGrid');
    }

    /**
     * Build the JSON payload for the SendGrid v3 API.
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

        $from = ['email' => $fromAddress];

        if ($fromName !== '') {
            $from['name'] = $fromName;
        }

        $to = ['email' => $mailable->getToAddress()];

        if ($mailable->getToName() !== '') {
            $to['name'] = $mailable->getToName();
        }

        $content = [];

        if ($mailable->getTextBody() !== '') {
            $content[] = ['type' => 'text/plain', 'value' => $mailable->getTextBody()];
        }

        if ($mailable->getHtmlBody() !== '') {
            $content[] = ['type' => 'text/html', 'value' => $mailable->getHtmlBody()];
        }

        $payload = [
            'personalizations' => [['to' => [$to]]],
            'from' => $from,
            'subject' => $mailable->getSubject(),
            'content' => $content,
        ];

        $attachments = $this->buildAttachments($mailable->getAttachments());

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    /**
     * Encode attachments as base64 for the SendGrid API payload.
     *
     * @param Attachment[] $attachments
     *
     * @throws MailException When an attachment file is not readable.
     *
     * @return list<array<string, string>>
     */
    private function buildAttachments(array $attachments): array
    {
        $result = [];

        foreach ($attachments as $attachment) {
            $result[] = [
                'content' => base64_encode($attachment->getContent()),
                'type' => $attachment->getMimeType(),
                'filename' => $attachment->getName(),
                'disposition' => 'attachment',
            ];
        }

        return $result;
    }
}
