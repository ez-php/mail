<?php

declare(strict_types=1);

namespace EzPhp\Mail;

/**
 * Class Mailable
 *
 * Fluent builder for outgoing mail messages. Can be used directly for simple messages
 * or extended by application-specific mail classes that configure themselves in their
 * constructor.
 *
 * Example — inline:
 *   $mail = (new Mailable())
 *       ->to('user@example.com', 'Alice')
 *       ->subject('Welcome!')
 *       ->text('Hello Alice');
 *
 * Example — extended:
 *   class WelcomeMail extends Mailable {
 *       public function __construct(User $user) {
 *           $this->to($user->email, $user->name)->subject('Welcome!')->text("Hi {$user->name}");
 *       }
 *   }
 *
 * @package EzPhp\Mail
 */
class Mailable
{
    /**
     * @var string Recipient e-mail address.
     */
    private string $toAddress = '';

    /**
     * @var string Optional recipient display name.
     */
    private string $toName = '';

    /**
     * @var string Sender e-mail address (overrides the driver default when set).
     */
    private string $fromAddress = '';

    /**
     * @var string Optional sender display name (overrides the driver default when set).
     */
    private string $fromName = '';

    /**
     * @var string Message subject line.
     */
    private string $subject = '';

    /**
     * @var string Plain-text body; optional when an HTML body is provided.
     */
    private string $textBody = '';

    /**
     * @var string HTML body; optional when a plain-text body is provided.
     */
    private string $htmlBody = '';

    /**
     * @var list<Attachment> File attachments.
     */
    private array $attachments = [];

    /**
     * Set the recipient.
     *
     * @param string $address E-mail address.
     * @param string $name    Optional display name.
     *
     * @return static
     */
    public function to(string $address, string $name = ''): static
    {
        $this->toAddress = $address;
        $this->toName = $name;

        return $this;
    }

    /**
     * Override the sender address for this message.
     * When not called the driver's configured from address is used.
     *
     * @param string $address E-mail address.
     * @param string $name    Optional display name.
     *
     * @return static
     */
    public function from(string $address, string $name = ''): static
    {
        $this->fromAddress = $address;
        $this->fromName = $name;

        return $this;
    }

    /**
     * Set the subject line.
     *
     * @param string $subject
     *
     * @return static
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set the plain-text body.
     * Can be combined with html() to produce a multipart/alternative message.
     *
     * @param string $body
     *
     * @return static
     */
    public function text(string $body): static
    {
        $this->textBody = $body;

        return $this;
    }

    /**
     * Set the HTML body.
     * Can be combined with text() to produce a multipart/alternative message.
     *
     * @param string $body
     *
     * @return static
     */
    public function html(string $body): static
    {
        $this->htmlBody = $body;

        return $this;
    }

    /**
     * Add a file attachment.
     *
     * @param string $path Absolute path to the file on disk.
     * @param string $name Optional display filename; defaults to basename($path).
     *
     * @return static
     */
    public function attach(string $path, string $name = ''): static
    {
        $this->attachments[] = new Attachment($path, $name);

        return $this;
    }

    /**
     * Return the recipient address.
     *
     * @return string
     */
    public function getToAddress(): string
    {
        return $this->toAddress;
    }

    /**
     * Return the optional recipient display name.
     *
     * @return string
     */
    public function getToName(): string
    {
        return $this->toName;
    }

    /**
     * Return the sender address override (empty string = use driver default).
     *
     * @return string
     */
    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    /**
     * Return the sender display name override (empty string = use driver default).
     *
     * @return string
     */
    public function getFromName(): string
    {
        return $this->fromName;
    }

    /**
     * Return the subject line.
     *
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Return the plain-text body.
     *
     * @return string
     */
    public function getTextBody(): string
    {
        return $this->textBody;
    }

    /**
     * Return the HTML body.
     *
     * @return string
     */
    public function getHtmlBody(): string
    {
        return $this->htmlBody;
    }

    /**
     * Return all attached files.
     *
     * @return list<Attachment>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
