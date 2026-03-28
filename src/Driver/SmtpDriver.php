<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use EzPhp\Mail\MailException;
use EzPhp\Mail\MimeBuilder;

/**
 * Class SmtpDriver
 *
 * Delivers mail via a raw SMTP connection using PHP's stream_socket_client().
 * No third-party library is required — the driver implements the SMTP protocol
 * (RFC 5321) directly.
 *
 * Supported encryption modes:
 * - 'ssl'  — wraps the connection in SSL/TLS from the start (port 465)
 * - 'tls'  — connects plain and upgrades via STARTTLS (port 587)
 * - 'none' — plain-text connection (port 25; not recommended in production)
 *
 * Authentication: AUTH LOGIN using base64-encoded credentials.
 * When username is an empty string, AUTH is skipped.
 *
 * @package EzPhp\Mail\Driver
 */
final class SmtpDriver implements MailerInterface
{
    /**
     * @param string      $host        SMTP server hostname.
     * @param int         $port        SMTP server port.
     * @param string      $username    AUTH LOGIN username; empty string skips authentication.
     * @param string      $password    AUTH LOGIN password.
     * @param string      $encryption  One of 'ssl', 'tls', or 'none'.
     * @param string      $fromAddress Default sender address (overridden by Mailable::from()).
     * @param string      $fromName    Default sender display name (overridden by Mailable::from()).
     * @param MimeBuilder $mime        MIME message builder.
     * @param int         $timeout     Socket connect/read timeout in seconds.
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $encryption,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly MimeBuilder $mime,
        private readonly int $timeout = 30,
    ) {
    }

    /**
     * Connect to the SMTP server and deliver the given mailable.
     *
     * @param Mailable $mailable
     *
     * @throws MailException On connection, authentication, or protocol errors.
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $socket = $this->openSocket();

        try {
            $this->transmit($socket, $mailable);
        } finally {
            fclose($socket);
        }
    }

    /**
     * Open a TCP (or SSL) socket to the configured SMTP server.
     *
     * @throws MailException When the connection cannot be established.
     *
     * @return resource
     */
    private function openSocket()
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $address = "{$scheme}://{$this->host}:{$this->port}";

        $errorCode = 0;
        $errorMessage = '';

        $socket = @stream_socket_client(
            $address,
            $errorCode,
            $errorMessage,
            (float) $this->timeout,
        );

        if ($socket === false) {
            throw new MailException(
                "Cannot connect to SMTP server {$this->host}:{$this->port}: {$errorMessage}"
            );
        }

        stream_set_timeout($socket, $this->timeout);

        return $socket;
    }

    /**
     * Perform the full SMTP transaction: greeting, EHLO, optional STARTTLS,
     * optional AUTH, MAIL FROM, RCPT TO, DATA, QUIT.
     *
     * @param resource $socket   Open stream socket.
     * @param Mailable $mailable Message to deliver.
     *
     * @throws MailException On any unexpected server response.
     *
     * @return void
     */
    private function transmit($socket, Mailable $mailable): void
    {
        $this->expect($socket, 220);

        $hostname = gethostname();
        $hostname = is_string($hostname) && $hostname !== '' ? $hostname : 'localhost';

        $this->write($socket, "EHLO {$hostname}\r\n");
        $this->expect($socket, 250);

        if ($this->encryption === 'tls') {
            $this->write($socket, "STARTTLS\r\n");
            $this->expect($socket, 220);

            if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === false) {
                throw new MailException('STARTTLS negotiation failed');
            }

            $this->write($socket, "EHLO {$hostname}\r\n");
            $this->expect($socket, 250);
        }

        if ($this->username !== '') {
            $this->write($socket, "AUTH LOGIN\r\n");
            $this->expect($socket, 334);
            $this->write($socket, base64_encode($this->username) . "\r\n");
            $this->expect($socket, 334);
            $this->write($socket, base64_encode($this->password) . "\r\n");
            $this->expect($socket, 235);
        }

        $fromAddress = $mailable->getFromAddress() !== '' ? $mailable->getFromAddress() : $this->fromAddress;

        $this->write($socket, "MAIL FROM:<{$fromAddress}>\r\n");
        $this->expect($socket, 250);

        $this->write($socket, "RCPT TO:<{$mailable->getToAddress()}>\r\n");
        $this->expect($socket, 250);

        $this->write($socket, "DATA\r\n");
        $this->expect($socket, 354);

        $message = $this->mime->build($mailable, $this->fromAddress, $this->fromName);
        // RFC 5321 dot-stuffing: lines starting with '.' must be prefixed with an extra '.'
        $message = preg_replace('/^\./m', '..', $message) ?? $message;

        $this->write($socket, $message . "\r\n.\r\n");
        $this->expect($socket, 250);

        $this->write($socket, "QUIT\r\n");
    }

    /**
     * Read a (possibly multi-line) SMTP response, assert its status code, and
     * return the full response string.
     *
     * @param resource $socket       Open stream socket.
     * @param int      $expectedCode Expected three-digit SMTP status code.
     *
     * @throws MailException When the response code does not match.
     *
     * @return string
     */
    private function expect($socket, int $expectedCode): string
    {
        $response = $this->read($socket);
        $code = (int) substr($response, 0, 3);

        if ($code !== $expectedCode) {
            throw new MailException(
                "Unexpected SMTP response (expected {$expectedCode}, got {$code}): " . trim($response)
            );
        }

        return $response;
    }

    /**
     * Read a complete (possibly multi-line) SMTP response from the socket.
     *
     * @param resource $socket Open stream socket.
     *
     * @throws MailException When the connection is lost during read.
     *
     * @return string
     */
    private function read($socket): string
    {
        $response = '';

        while (true) {
            $line = fgets($socket, 512);

            if ($line === false) {
                throw new MailException('SMTP connection lost while reading response');
            }

            $response .= $line;

            // Multi-line responses use '-' as the 4th character; last line uses ' '
            if (strlen($line) >= 4 && $line[3] !== '-') {
                break;
            }
        }

        return $response;
    }

    /**
     * Write data to the socket.
     *
     * @param resource $socket Open stream socket.
     * @param string   $data   Data to write.
     *
     * @throws MailException When the write fails.
     *
     * @return void
     */
    private function write($socket, string $data): void
    {
        if (fwrite($socket, $data) === false) {
            throw new MailException('Failed to write to SMTP connection');
        }
    }
}
