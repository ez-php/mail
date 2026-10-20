<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use CURLFile;
use CURLStringFile;
use EzPhp\Mail\Driver\Http\ApiTransportInterface;
use EzPhp\Mail\Driver\Http\CurlApiTransport;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use EzPhp\Mail\MailException;

/**
 * Class MailgunDriver
 *
 * Delivers mail via the Mailgun HTTP API (v3).
 * No third-party library is required — the driver uses PHP's built-in cURL extension.
 *
 * API endpoint:
 * - US region (default): https://api.mailgun.net/v3/{domain}/messages
 * - EU region:           https://api.eu.mailgun.net/v3/{domain}/messages
 *
 * Authentication: HTTP Basic auth using "api" as the username and the private
 * API key as the password.
 *
 * Attachments are sent as multipart/form-data file fields named attachment[0],
 * attachment[1], etc. Mailgun accepts indexed array notation for multiple files.
 * In-memory attachments (Mailable::attachData()) go up as CURLStringFile.
 *
 * The HTTP request goes through an ApiTransportInterface (CurlApiTransport by
 * default), so the error paths are unit-tested with a fake transport; real
 * delivery still needs a Mailgun account or Sandbox domain.
 *
 * @package EzPhp\Mail\Driver
 */
final class MailgunDriver implements MailerInterface
{
    private readonly ApiTransportInterface $transport;

    /**
     * @param string $domain      Mailgun sending domain (e.g. 'mail.example.com').
     * @param string $apiKey      Mailgun private API key (starts with 'key-').
     * @param string $fromAddress Default sender address (overridden by Mailable::from()).
     * @param string $fromName    Default sender display name (overridden by Mailable::from()).
     * @param string $region      API region: 'us' (default) or 'eu'.
     * @param int    $timeout     Total request timeout in seconds (`mail.timeout`).
     * @param int    $connectTimeout Connection-phase timeout in seconds, so an unreachable API fails fast.
     * @param ApiTransportInterface|null $transport HTTP transport; null = CurlApiTransport.
     */
    public function __construct(
        private readonly string $domain,
        private readonly string $apiKey,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $region = 'us',
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        ?ApiTransportInterface $transport = null,
    ) {
        $this->transport = $transport ?? new CurlApiTransport();
    }

    /**
     * Deliver the given mailable via the Mailgun HTTP API.
     *
     * @param Mailable $mailable
     *
     * @throws MailException On transport errors, unreadable attachments, or non-2xx responses.
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $endpoint = $this->region === 'eu'
            ? "https://api.eu.mailgun.net/v3/{$this->domain}/messages"
            : "https://api.mailgun.net/v3/{$this->domain}/messages";

        $fields = $this->buildFields($mailable);

        $this->transport
            ->post($endpoint, $fields, [], "api:{$this->apiKey}", $this->timeout, $this->connectTimeout)
            ->assertDelivered('Mailgun');
    }

    /**
     * Build the multipart form fields for the Mailgun API request.
     *
     * @param Mailable $mailable
     *
     * @throws MailException When an attachment file is not readable.
     *
     * @return array<string, string|CURLFile|CURLStringFile>
     */
    private function buildFields(Mailable $mailable): array
    {
        $from = $mailable->getFromAddress() !== '' ? $mailable->getFromAddress() : $this->fromAddress;
        $fromName = $mailable->getFromName() !== '' ? $mailable->getFromName() : $this->fromName;
        $fromHeader = $fromName !== '' ? "{$fromName} <{$from}>" : $from;

        $to = $mailable->getToName() !== ''
            ? "{$mailable->getToName()} <{$mailable->getToAddress()}>"
            : $mailable->getToAddress();

        $fields = [
            'from' => $fromHeader,
            'to' => $to,
            'subject' => $mailable->getSubject(),
        ];

        if ($mailable->getTextBody() !== '') {
            $fields['text'] = $mailable->getTextBody();
        }

        if ($mailable->getHtmlBody() !== '') {
            $fields['html'] = $mailable->getHtmlBody();
        }

        foreach ($mailable->getAttachments() as $index => $attachment) {
            $data = $attachment->getData();

            if ($data !== null) {
                $fields["attachment[{$index}]"] = new CURLStringFile($data, $attachment->getName(), $attachment->getMimeType());

                continue;
            }

            if (!is_readable($attachment->getPath())) {
                throw new MailException(
                    "Cannot read attachment file: {$attachment->getPath()}"
                );
            }

            $fields["attachment[{$index}]"] = new CURLFile(
                $attachment->getPath(),
                '',
                $attachment->getName(),
            );
        }

        return $fields;
    }
}
