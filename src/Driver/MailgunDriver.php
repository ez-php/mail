<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use CURLFile;
use CurlHandle;
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
 *
 * This driver is not covered by automated unit tests — a live Mailgun account
 * (or Mailgun Sandbox domain) is required. Integration-test it against a Sandbox
 * domain or use the LogDriver during development.
 *
 * @package EzPhp\Mail\Driver
 */
final class MailgunDriver implements MailerInterface
{
    /**
     * @param string $domain      Mailgun sending domain (e.g. 'mail.example.com').
     * @param string $apiKey      Mailgun private API key (starts with 'key-').
     * @param string $fromAddress Default sender address (overridden by Mailable::from()).
     * @param string $fromName    Default sender display name (overridden by Mailable::from()).
     * @param string $region      API region: 'us' (default) or 'eu'.
     */
    public function __construct(
        private readonly string $domain,
        private readonly string $apiKey,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $region = 'us',
    ) {
    }

    /**
     * Deliver the given mailable via the Mailgun HTTP API.
     *
     * @param Mailable $mailable
     *
     * @throws MailException On cURL errors, unreadable attachments, or non-2xx responses.
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $endpoint = $this->region === 'eu'
            ? "https://api.eu.mailgun.net/v3/{$this->domain}/messages"
            : "https://api.mailgun.net/v3/{$this->domain}/messages";

        $fields = $this->buildFields($mailable);

        $ch = curl_init();

        if ($ch === false) {
            throw new MailException('Failed to initialise cURL handle');
        }

        $this->execute($ch, $endpoint, $fields);
    }

    /**
     * Build the multipart form fields for the Mailgun API request.
     *
     * @param Mailable $mailable
     *
     * @throws MailException When an attachment file is not readable.
     *
     * @return array<string, string|CURLFile>
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
            if (!is_readable($attachment->getPath())) {
                throw new MailException(
                    "Attachment not readable: {$attachment->getPath()}"
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

    /**
     * Configure the cURL handle and execute the Mailgun API request.
     *
     * @param CurlHandle                     $ch       Initialised cURL handle.
     * @param non-empty-string               $endpoint Full API URL.
     * @param array<string, string|CURLFile> $fields   Form fields to POST.
     *
     * @throws MailException On cURL errors or non-2xx HTTP responses.
     *
     * @return void
     */
    private function execute(CurlHandle $ch, string $endpoint, array $fields): void
    {
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_USERPWD => "api:{$this->apiKey}",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError !== '') {
            throw new MailException("Mailgun delivery failed: {$curlError}");
        }

        if (!is_string($response)) {
            throw new MailException('Mailgun returned an unexpected non-string response');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new MailException(
                "Mailgun rejected the message (HTTP {$statusCode}): {$response}"
            );
        }
    }
}
