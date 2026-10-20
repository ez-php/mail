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
     * @param Attachment $attachment File or in-memory content to encode.
     * @param string     $boundary   Surrounding MIME boundary token.
     *
     * @throws MailException If the file cannot be read.
     *
     * @return string
     */
    private function attachmentPart(Attachment $attachment, string $boundary): string
    {
        $name = $this->stripHeaderInjection($attachment->getName());
        $encoded = chunk_split(base64_encode($attachment->getContent()), 76, "\r\n");
        $mime = $this->safeMimeType($attachment->getMimeType());

        return "--{$boundary}\r\n"
            . "Content-Type: {$mime}; name=\"" . $this->quoteParameter($this->encodeHeader($name)) . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'Content-Disposition: attachment; ' . $this->filenameParameter($name) . "\r\n"
            . "\r\n"
            . $encoded;
    }

    /**
     * Return the MIME type when it is a plain `type/subtype` token pair, else
     * `application/octet-stream` — the value comes from the caller (or an
     * upload's client-supplied type) and is interpolated into a header line.
     *
     * @param string $mime Raw MIME type.
     *
     * @return string
     */
    private function safeMimeType(string $mime): string
    {
        return preg_match('~^[A-Za-z0-9][A-Za-z0-9!#$&^_.+-]*/[A-Za-z0-9][A-Za-z0-9!#$&^_.+-]*$~', $mime) === 1
            ? $mime
            : 'application/octet-stream';
    }

    /**
     * The Content-Disposition `filename` parameter: a quoted string for ASCII
     * names, RFC 2231 `filename*=UTF-8''…` percent-encoding otherwise.
     *
     * @param string $name Filename with control characters already stripped.
     *
     * @return string
     */
    private function filenameParameter(string $name): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $name) === 1) {
            return 'filename="' . $this->quoteParameter($name) . '"';
        }

        return "filename*=UTF-8''" . rawurlencode($name);
    }

    /**
     * Escape `\` and `"` for use inside an RFC 2045 quoted-string parameter.
     *
     * @param string $value
     *
     * @return string
     */
    private function quoteParameter(string $value): string
    {
        return addcslashes($value, '"\\');
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
        $address = $this->stripHeaderInjection($address);

        if ($name !== '') {
            return "{$header}: " . $this->encodeHeader($name) . " <{$address}>\r\n";
        }

        return "{$header}: {$address}\r\n";
    }

    /**
     * RFC 2047 encode a header value when it contains non-ASCII characters.
     *
     * Also strips CR/LF and other control characters first, so a value that
     * originated from user input cannot inject additional headers or
     * recipients into the outgoing message (header injection).
     *
     * @param string $value Raw header value.
     *
     * @return string
     */
    private function encodeHeader(string $value): string
    {
        $value = $this->stripHeaderInjection($value);

        if (preg_match('/^[\x00-\x7F]*$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * Remove CR, LF, and other C0 control characters from a value that will
     * be interpolated into a MIME header line, preventing header/recipient
     * injection via embedded newlines.
     *
     * @param string $value Raw value.
     *
     * @return string Value with control characters removed.
     */
    private function stripHeaderInjection(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
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
