<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver\Http;

use EzPhp\Mail\MailException;

/**
 * Class ApiResponse
 *
 * Raw outcome of an ApiTransportInterface request: the HTTP status, the body
 * (null when the transport produced none) and a transport-level error message
 * ('' when the request reached the server).
 *
 * @package EzPhp\Mail\Driver\Http
 */
final readonly class ApiResponse
{
    /**
     * @param int         $status HTTP status code (0 when no response was received).
     * @param string|null $body   Response body, or null when none was received.
     * @param string      $error  Transport error (e.g. cURL's message); '' on success.
     */
    public function __construct(
        public int $status,
        public ?string $body,
        public string $error = '',
    ) {
    }

    /**
     * Throw unless the request reached the API and was answered with a 2xx status.
     *
     * @param string $provider Provider name used in the message, e.g. 'Mailgun'.
     *
     * @throws MailException On a transport error, a missing body, or a non-2xx status.
     *
     * @return void
     */
    public function assertDelivered(string $provider): void
    {
        if ($this->error !== '') {
            throw new MailException("{$provider} delivery failed: {$this->error}");
        }

        if ($this->body === null) {
            throw new MailException("{$provider} returned an unexpected non-string response.");
        }

        if ($this->status < 200 || $this->status >= 300) {
            throw new MailException("{$provider} rejected the message (HTTP {$this->status}): {$this->body}");
        }
    }
}
