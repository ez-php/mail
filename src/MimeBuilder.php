<?php

declare(strict_types=1);

namespace EzPhp\Mail;

/**
 * Class MimeBuilder
 *
 * Builds a fully RFC 2822 / MIME-compliant message string suitable for the
 * SMTP DATA command from a Mailable value object.
 *
 * Supported body combinations:
 * - Plain text only           → Content-Type: text/plain (quoted-printable)
 * - HTML only                 → Content-Type: text/html  (quoted-printable)
 * - Plain text + HTML         → multipart/alternative
 * - Any of the above + files  → wrapped in multipart/mixed
 *
 * @package EzPhp\Mail
 */
final class MimeBuilder
{
    /**
     * Build the complete DATA payload (headers + blank line + body) for the given mailable.
     *
     * @param Mailable $mailable          Message to encode.
     * @param string   $defaultFromAddress Sender address used when Mailable::from() was not called.
     * @param string   $defaultFromName    Sender display name used when Mailable::from() was not called.
     *
     * @throws MailException If an attachment file cannot be read.
     *
     * @return string Full message string ready to be written after SMTP DATA.
     */
    public function build(Mailable $mailable, string $defaultFromAddress, string $defaultFromName): string
    {
        $fromAddress = $mailable->getFromAddress() !== '' ? $mailable->getFromAddress() : $defaultFromAddress;
        $fromName = $mailable->getFromName() !== '' ? $mailable->getFromName() : $defaultFromName;

        $text = $mailable->getTextBody();
        $html = $mailable->getHtmlBody();
        $attachments = $mailable->getAttachments();

        // ── Build inner content (text / html / alternative) ──────────────────
        if ($text !== '' && $html !== '') {
            $altBoundary = $this->boundary();
            $contentType = "multipart/alternative; boundary=\"{$altBoundary}\"";
            $bodyHeaders = '';
            $body = $this->alternativePart($text, $html, $altBoundary);
        } elseif ($html !== '') {
            $contentType = 'text/html; charset=UTF-8';
            $bodyHeaders = "Content-Transfer-Encoding: quoted-printable\r\n";
            $body = quoted_printable_encode($html);
        } else {
            $contentType = 'text/plain; charset=UTF-8';
            $bodyHeaders = "Content-Transfer-Encoding: quoted-printable\r\n";
            $body = quoted_printable_encode($text);
        }

        // ── Wrap in multipart/mixed when attachments are present ──────────────
        if ($attachments !== []) {
            $mixedBoundary = $this->boundary();
            $mixedBody = "--{$mixedBoundary}\r\n"
                . "Content-Type: {$contentType}\r\n"
                . $bodyHeaders
                . "\r\n"
                . $body . "\r\n";

            foreach ($attachments as $attachment) {
                $mixedBody .= $this->attachmentPart($attachment, $mixedBoundary);
            }

            $mixedBody .= "--{$mixedBoundary}--\r\n";
            $contentType = "multipart/mixed; boundary=\"{$mixedBoundary}\"";
            $bodyHeaders = '';
            $body = $mixedBody;
        }

        // ── Assemble top-level headers ────────────────────────────────────────
        $headers = $this->formatAddress('From', $fromAddress, $fromName)
            . $this->formatAddress('To', $mailable->getToAddress(), $mailable->getToName())
            . 'Subject: ' . $this->encodeHeader($mailable->getSubject()) . "\r\n"
            . 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: {$contentType}\r\n"
            . $bodyHeaders;

        return $headers . "\r\n" . $body;
    }

    /**
     * Build a multipart/alternative body containing plain-text and HTML parts.
     *
     * @param string $text     Plain-text body.
     * @param string $html     HTML body.
     * @param string $boundary MIME boundary token.
     *
     * @return string
     */
    private function alternativePart(string $text, string $html, string $boundary): string
    {
        return "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "\r\n"
            . quoted_printable_encode($text) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "\r\n"
            . quoted_printable_encode($html) . "\r\n"
            . "--{$boundary}--\r\n";
    }

    /**
     * Build a single base64-encoded attachment MIME part.
     *
     * @param Attachment $attachment File to encode.
     * @param string     $boundary   Surrounding MIME boundary token.
     *
     * @throws MailException If the file cannot be read.
     *
     * @return string
     */
    private function attachmentPart(Attachment $attachment, string $boundary): string
    {
        $path = $attachment->getPath();

        if (!is_readable($path)) {
            throw new MailException("Cannot read attachment file: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new MailException("Cannot read attachment file: {$path}");
        }

        $name = $attachment->getName();
        $encoded = chunk_split(base64_encode($content), 76, "\r\n");
        $mime = mime_content_type($path);
        $mime = is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';

        return "--{$boundary}\r\n"
            . "Content-Type: {$mime}; name=\"{$name}\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=\"{$name}\"\r\n"
            . "\r\n"
            . $encoded;
    }

    /**
     * Format an RFC 2822 address header line.
     *
     * @param string $header  Header name (e.g. 'From', 'To').
     * @param string $address E-mail address.
     * @param string $name    Optional display name.
     *
     * @return string
     */
    private function formatAddress(string $header, string $address, string $name): string
    {
        if ($name !== '') {
            return "{$header}: " . $this->encodeHeader($name) . " <{$address}>\r\n";
        }

        return "{$header}: {$address}\r\n";
    }

    /**
     * RFC 2047 encode a header value when it contains non-ASCII characters.
     *
     * @param string $value Raw header value.
     *
     * @return string
     */
    private function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x00-\x7F]*$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * Generate a random MIME boundary token.
     *
     * @return string
     */
    private function boundary(): string
    {
        return bin2hex(random_bytes(16));
    }
}
