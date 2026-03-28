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
 * Example — view integration (requires MailViewInterface to be bound):
 *   $mail = (new Mailable())
 *       ->to('user@example.com')
 *       ->subject('Welcome!')
 *       ->view('emails.welcome', ['user' => $user]);
 *
 * @package EzPhp\Mail
 */
class Mailable
{
    /**
     * Optional view renderer for template-based HTML bodies.
     * Set via setViewRenderer() from MailServiceProvider::boot().
     *
     * @var MailViewInterface|null
     */
    private static ?MailViewInterface $viewRenderer = null;

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
     * @var string View template name for HTML body rendering (e.g. 'emails.welcome').
     */
    private string $viewName = '';

    /**
     * @var array<string, mixed> Data to pass to the view template.
     */
    private array $viewData = [];

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
     * Wire the view renderer used for template-based HTML bodies.
     * Called by MailServiceProvider::boot() when a MailViewInterface is available.
     *
     * @param MailViewInterface $renderer
     *
     * @return void
     */
    public static function setViewRenderer(MailViewInterface $renderer): void
    {
        self::$viewRenderer = $renderer;
    }

    /**
     * Clear the view renderer — used in test tearDown to prevent state leaking.
     *
     * @return void
     */
    public static function resetViewRenderer(): void
    {
        self::$viewRenderer = null;
    }

    /**
     * Set the HTML body using a view template.
     * Delegates rendering to the registered MailViewInterface at send time.
     * Throws MailException if called without a view renderer configured.
     *
     * @param string               $name View template name (e.g. 'emails.welcome').
     * @param array<string, mixed> $data Variables to pass to the template.
     *
     * @return static
     */
    public function view(string $name, array $data = []): static
    {
        $this->viewName = $name;
        $this->viewData = $data;

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
     * When a view template has been set via view(), the renderer is invoked and
     * the result is returned. Throws MailException when a view name is set but no
     * MailViewInterface has been configured via setViewRenderer().
     *
     * @throws MailException When a view is set but no renderer is configured.
     *
     * @return string
     */
    public function getHtmlBody(): string
    {
        if ($this->viewName !== '') {
            if (self::$viewRenderer === null) {
                throw new MailException(
                    'Mailable::view() was called but no view renderer is configured. ' .
                    'Bind MailViewInterface in a service provider and ensure MailServiceProvider is registered.'
                );
            }

            return self::$viewRenderer->render($this->viewName, $this->viewData);
        }

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
