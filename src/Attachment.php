<?php

declare(strict_types=1);

namespace EzPhp\Mail;

/**
 * Class Attachment
 *
 * Immutable value object representing a file to be attached to a Mailable —
 * either a file on disk (`new Attachment($path, $name)`) or in-memory bytes
 * (`Attachment::fromData($content, $name, $mimeType)`), e.g. a rendered PDF or
 * a file read through ez-php/storage, which has no local path for S3/GCS.
 * The display name defaults to the file's basename when not explicitly set.
 *
 * @package EzPhp\Mail
 */
final class Attachment
{
    /**
     * @param string      $path     Absolute path to the file on disk ('' for in-memory data).
     * @param string      $name     Display filename shown to the recipient; defaults to basename($path).
     * @param string|null $data     Raw attachment bytes; when set, $path is not read.
     * @param string      $mimeType Explicit MIME type; '' = detect from the file (path attachments only).
     */
    public function __construct(
        private readonly string $path,
        private readonly string $name,
        private readonly ?string $data = null,
        private readonly string $mimeType = '',
    ) {
    }

    /**
     * Create an attachment from in-memory content.
     *
     * @param string $content  Raw attachment bytes.
     * @param string $name     Display filename shown to the recipient (required — there is no path).
     * @param string $mimeType MIME type, e.g. 'application/pdf'.
     *
     * @throws MailException When $name is empty.
     *
     * @return self
     */
    public static function fromData(string $content, string $name, string $mimeType = 'application/octet-stream'): self
    {
        if ($name === '') {
            throw new MailException('An in-memory attachment needs a filename.');
        }

        return new self('', $name, $content, $mimeType);
    }

    /**
     * Return the absolute file path ('' for an in-memory attachment).
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return the display filename shown to the recipient.
     * Falls back to the file's basename when no explicit name was provided.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name !== '' ? $this->name : basename($this->path);
    }

    /**
     * Return the in-memory bytes, or null for a file attachment.
     *
     * @return string|null
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * Return the attachment bytes — the in-memory data, or the file's contents.
     *
     * @throws MailException When the file cannot be read.
     *
     * @return string
     */
    public function getContent(): string
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $content = is_readable($this->path) ? file_get_contents($this->path) : false;

        if ($content === false) {
            throw new MailException("Cannot read attachment file: {$this->path}");
        }

        return $content;
    }

    /**
     * Return the MIME type: the explicit one, else detected from the file,
     * else 'application/octet-stream'.
     *
     * @return string
     */
    public function getMimeType(): string
    {
        if ($this->mimeType !== '') {
            return $this->mimeType;
        }

        $detected = $this->data === null && is_readable($this->path) ? mime_content_type($this->path) : false;

        return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }
}
