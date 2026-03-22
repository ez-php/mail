<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;

/**
 * Class LogDriver
 *
 * Writes a human-readable summary of every outgoing message to a log file
 * instead of delivering it. Designed for local development and CI environments
 * where real delivery is undesirable.
 *
 * Each entry records the timestamp, recipient, subject, body presence, and
 * attachment count. The log file is created (including its parent directory)
 * on first write.
 *
 * @package EzPhp\Mail\Driver
 */
final class LogDriver implements MailerInterface
{
    /**
     * @param string $logPath Absolute path to the log file.
     *                        When the path is an empty string the message is written via error_log().
     */
    public function __construct(private readonly string $logPath)
    {
    }

    /**
     * Append a summary of the given mailable to the log file (or error_log).
     *
     * @param Mailable $mailable
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $to = $mailable->getToName() !== ''
            ? $mailable->getToName() . ' <' . $mailable->getToAddress() . '>'
            : $mailable->getToAddress();

        $entry = sprintf(
            "[%s] To: %s | Subject: %s | Text: %s | HTML: %s | Attachments: %d\n",
            date('Y-m-d H:i:s'),
            $to,
            $mailable->getSubject(),
            $mailable->getTextBody() !== '' ? 'yes' : 'no',
            $mailable->getHtmlBody() !== '' ? 'yes' : 'no',
            count($mailable->getAttachments()),
        );

        if ($this->logPath === '') {
            error_log($entry);

            return;
        }

        $dir = dirname($this->logPath);

        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }
}
