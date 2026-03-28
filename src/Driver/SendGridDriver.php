<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use CurlHandle;
use EzPhp\Mail\Attachment;
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
 * This driver is not covered by automated unit tests — a live SendGrid account
 * or Twilio SendGrid Sandbox is required. Integration-test it against a verified
 * Sender identity or use the LogDriver during development.
 *
 * @package EzPhp\Mail\Driver
 */
final class SendGridDriver implements MailerInterface
{
    private const string ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    /**
     * @param string $apiKey      SendGrid API key with the Mail Send permission.
     * @param string $fromAddress Default sender address (overridden by Mailable::from()).
     * @param string $fromName    Default sender display name (overridden by Mailable::from()).
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
    }

    /**
     * Deliver the given mailable via the SendGrid v3 Mail Send API.
     *
     * @param Mailable $mailable
     *
     * @throws MailException On cURL errors, unreadable attachments, or non-2xx responses.
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $payload = $this->buildPayload($mailable);

        $ch = curl_init();

        if ($ch === false) {
            throw new MailException('Failed to initialise cURL handle.');
        }

        $this->execute($ch, $payload);
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
            if (!is_readable($attachment->getPath())) {
                throw new MailException("Attachment not readable: {$attachment->getPath()}");
            }

            $content = file_get_contents($attachment->getPath());

            if ($content === false) {
                throw new MailException("Could not read attachment: {$attachment->getPath()}");
            }

            $mimeType = mime_content_type($attachment->getPath());

            $result[] = [
                'content' => base64_encode($content),
                'type' => is_string($mimeType) ? $mimeType : 'application/octet-stream',
                'filename' => $attachment->getName(),
                'disposition' => 'attachment',
            ];
        }

        return $result;
    }

    /**
     * Configure the cURL handle and execute the SendGrid API request.
     *
     * @param CurlHandle           $ch      Initialised cURL handle.
     * @param array<string, mixed> $payload JSON-serialisable request body.
     *
     * @throws MailException On cURL errors or non-2xx HTTP responses.
     *
     * @return void
     */
    private function execute(CurlHandle $ch, array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        curl_setopt_array($ch, [
            CURLOPT_URL => self::ENDPOINT,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError !== '') {
            throw new MailException("SendGrid delivery failed: {$curlError}");
        }

        if (!is_string($response)) {
            throw new MailException('SendGrid returned an unexpected non-string response.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new MailException(
                "SendGrid rejected the message (HTTP {$statusCode}): {$response}"
            );
        }
    }
}
