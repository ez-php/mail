<?php

declare(strict_types=1);

namespace EzPhp\Mail;

/**
 * Class Attachment
 *
 * Immutable value object representing a file to be attached to a Mailable.
 * The display name defaults to the file's basename when not explicitly set.
 *
 * @package EzPhp\Mail
 */
final class Attachment
{
    /**
     * @param string $path Absolute path to the file on disk.
     * @param string $name Display filename shown to the recipient; defaults to basename($path).
     */
    public function __construct(
        private readonly string $path,
        private readonly string $name,
    ) {
    }

    /**
     * Return the absolute file path.
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
}
